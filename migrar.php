<?php
require_once __DIR__ . '/includes/db.php';
$db = getDB();

$ok = []; $erros = [];

// ── 1. Recriar tabela escalas com entrada/saida nullable ──────────────────────
try {
    $cols   = $db->query("PRAGMA table_info(escalas)")->fetchAll(PDO::FETCH_ASSOC);
    $nomes  = array_column($cols, 'name');

    // Verifica se entrada ainda é NOT NULL (problema antigo)
    $entradaCol = array_filter($cols, fn($c) => $c['name'] === 'entrada');
    $entradaCol = array_values($entradaCol);
    $precisaRecriar = !empty($entradaCol) && $entradaCol[0]['notnull'] == 1;

    if ($precisaRecriar) {
        $db->exec("BEGIN");
        $db->exec("ALTER TABLE escalas RENAME TO escalas_old");
        $db->exec("
            CREATE TABLE escalas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                funcionario_id INTEGER NOT NULL,
                semana_inicio DATE NOT NULL,
                dia_semana INTEGER NOT NULL,
                folga INTEGER NOT NULL DEFAULT 0,
                entrada TIME,
                saida TIME,
                FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
                UNIQUE(funcionario_id, semana_inicio, dia_semana)
            )
        ");
        $db->exec("
            INSERT INTO escalas (id, funcionario_id, semana_inicio, dia_semana, folga, entrada, saida)
            SELECT id, funcionario_id, semana_inicio, dia_semana, COALESCE(folga,0), entrada, saida
            FROM escalas_old
        ");
        $db->exec("DROP TABLE escalas_old");
        $db->exec("COMMIT");
        $ok[] = "✅ Tabela <b>escalas</b> recriada — entrada/saída agora aceitam NULL.";
    } else {
        $ok[] = "✅ Estrutura da tabela <b>escalas</b> já está correta.";
    }

    // Adiciona coluna folga se não existir
    if (!in_array('folga', $nomes)) {
        $db->exec("ALTER TABLE escalas ADD COLUMN folga INTEGER NOT NULL DEFAULT 0");
        $ok[] = "✅ Coluna <b>folga</b> adicionada.";
    }

} catch (Exception $e) {
    try { $db->exec("ROLLBACK"); } catch(Exception $e2) {}
    try { $db->exec("ALTER TABLE escalas_old RENAME TO escalas"); } catch(Exception $e3) {}
    $erros[] = "❌ Erro na tabela escalas: " . htmlspecialchars($e->getMessage());
}

// ── 2. Remover funcionários fictícios do seed de teste ────────────────────────
$ficticios = ['60153612', '60153613', '60153614'];
$removidos = 0;
foreach ($ficticios as $crachaTeste) {
    $chk = $db->prepare("SELECT id, nome FROM funcionarios WHERE cracha = ?");
    $chk->execute([$crachaTeste]);
    $f = $chk->fetch();
    if ($f) {
        // Só remove se o nome ainda for o nome fictício padrão
        $nomesFicticios = ['João da Silva', 'Ana Costa', 'Pedro Souza'];
        if (in_array($f['nome'], $nomesFicticios)) {
            $db->prepare("DELETE FROM funcionarios WHERE id = ?")->execute([$f['id']]);
            $ok[] = "🗑️ Funcionário de teste removido: <b>{$f['nome']}</b> (crachá {$crachaTeste})";
            $removidos++;
        }
    }
}
if ($removidos === 0) {
    $ok[] = "✅ Nenhum funcionário fictício encontrado.";
}

// ── Resumo ────────────────────────────────────────────────────────────────────
$totalFuncs  = $db->query("SELECT COUNT(*) as c FROM funcionarios WHERE ativo=1")->fetch()['c'];
$estrutura   = $db->query("PRAGMA table_info(escalas)")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração – McEscala</title>
    <style>
        body { font-family:'Segoe UI',sans-serif; background:#111; color:#ccc; padding:30px 20px; max-width:600px; margin:0 auto; }
        h1   { color:#FFC72C; font-size:22px; margin-bottom:4px; }
        h2   { color:#888; font-size:13px; font-weight:400; margin-bottom:24px; }
        .msg { padding:10px 16px; border-radius:8px; margin-bottom:8px; font-size:14px; line-height:1.5; }
        .ok  { background:#1a3a1a; border:1px solid #2a5a2a; color:#4ade80; }
        .err { background:#3a1a1a; border:1px solid #5a2a2a; color:#f87171; }
        table { width:100%; border-collapse:collapse; margin-top:20px; font-size:13px; }
        th { background:#252525; color:#FFC72C; padding:8px 10px; text-align:left; }
        td { padding:7px 10px; border-bottom:1px solid #222; color:#aaa; }
        .btn { display:inline-block; margin-top:24px; padding:13px 28px; background:#FFC72C; color:#27251F; border-radius:10px; text-decoration:none; font-weight:700; font-size:15px; }
        .info { color:#888; font-size:13px; margin-top:14px; }
    </style>
</head>
<body>
<h1>🔧 Migração do Banco</h1>
<h2>McEscala – Correções e limpeza</h2>

<?php foreach ($ok    as $m): ?><div class="msg ok"><?= $m ?></div><?php endforeach; ?>
<?php foreach ($erros as $m): ?><div class="msg err"><?= $m ?></div><?php endforeach; ?>

<p class="info">Funcionários ativos no banco: <strong style="color:#fff"><?= $totalFuncs ?></strong></p>

<table>
    <tr><th>Coluna</th><th>Tipo</th><th>Padrão</th><th>NOT NULL</th></tr>
    <?php foreach ($estrutura as $col): ?>
    <tr>
        <td><?= htmlspecialchars($col['name']) ?></td>
        <td><?= htmlspecialchars($col['type'] ?: '—') ?></td>
        <td><?= htmlspecialchars($col['dflt_value'] ?? '—') ?></td>
        <td><?= $col['notnull'] ? '✓' : '—' ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if (empty($erros)): ?>
    <a href="admin.php" class="btn">✅ Pronto! Ir para o Admin</a>
<?php endif; ?>
</body>
</html>
