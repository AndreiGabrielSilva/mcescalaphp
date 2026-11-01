<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();
requireAdmin();

$admin = getCurrentAdmin();
$db    = getDB();
$tab   = $_GET['tab'] ?? 'escalas';
$msg   = '';
$msgType = '';

// ===== AÇÕES POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // SALVAR ESCALA EM LOTE (grade completa)
    if ($action === 'salvar_grade') {
        $semana = $_POST['semana_inicio'] ?? '';
        $dados  = $_POST['celula'] ?? []; // celula[funcId][dia] = 'trabalha|folga|none'
        $horas  = $_POST['horas']  ?? []; // horas[funcId][dia]  = 'HH:MM-HH:MM'

        if ($semana) {
            // IDs únicos que serão salvos
            $funcIds = array_unique(array_merge(array_keys($dados), array_keys($horas)));

            $del  = $db->prepare("DELETE FROM escalas WHERE funcionario_id = ? AND semana_inicio = ?");
            $ins  = $db->prepare("INSERT OR REPLACE INTO escalas (funcionario_id, semana_inicio, dia_semana, folga, entrada, saida) VALUES (?,?,?,?,?,?)");

            foreach ($funcIds as $fid) {
                $fid = (int)$fid;
                if (!$fid) continue;
                $del->execute([$fid, $semana]);
                for ($d = 0; $d <= 6; $d++) {
                    $tipo  = $dados[$fid][$d] ?? 'none';
                    $hstr  = $horas[$fid][$d]  ?? '08:00-16:00';
                    [$ent, $sai] = explode('-', $hstr . '-16:00');
                    if ($tipo === 'folga') {
                        $ins->execute([$fid, $semana, $d, 1, null, null]);
                    } elseif ($tipo === 'trabalha') {
                        $ins->execute([$fid, $semana, $d, 0, $ent ?: '08:00', $sai ?: '16:00']);
                    }
                }
            }
            $msg = 'Escala da semana salva com sucesso!';
            $msgType = 'success';
        }
        $tab = 'escalas';
    }

    // CADASTRAR FUNCIONÁRIO
    if ($action === 'cadastrar') {
        $nome   = trim($_POST['nome']   ?? '');
        $cracha = trim($_POST['cracha'] ?? '');
        $cargo  = $_POST['cargo']  ?? 'Atendente';
        $senha  = $_POST['senha']  ?? '';

        if (!$nome || !$cracha) {
            $msg = 'Nome e crachá são obrigatórios.'; $msgType = 'error';
        } else {
            $chk = $db->prepare("SELECT id FROM funcionarios WHERE cracha = ?");
            $chk->execute([$cracha]);
            if ($chk->fetch()) {
                $msg = 'Este crachá já está cadastrado.'; $msgType = 'error';
            } elseif (in_array($cargo, ['Gerente de Plantão','Administração']) && !$senha) {
                $msg = 'Gerente de Plantão e Administração precisam de senha.'; $msgType = 'error';
            } else {
                $hash = $senha ? password_hash($senha, PASSWORD_DEFAULT) : null;
                $db->prepare("INSERT INTO funcionarios (nome, cracha, cargo, senha_hash) VALUES (?,?,?,?)")->execute([$nome, $cracha, $cargo, $hash]);
                $msg = "Funcionário \"$nome\" cadastrado!"; $msgType = 'success';
            }
        }
        $tab = 'cadastro';
    }

    // DELETAR FUNCIONÁRIO
    if ($action === 'deletar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== $admin['id']) {
            $db->prepare("UPDATE funcionarios SET ativo = 0 WHERE id = ?")->execute([$id]);
            $msg = 'Funcionário removido.'; $msgType = 'success';
        }
        $tab = 'funcionarios';
    }
}

// ===== DADOS =====
$allEmployees = $db->query("SELECT * FROM funcionarios WHERE ativo = 1 ORDER BY nome")->fetchAll();
$weekOffset   = (int)($_GET['semana'] ?? 0);
$weekStart    = getWeekStart($weekOffset);

// Carrega TODA a escala da semana de uma vez
$allShifts = [];
if ($allEmployees) {
    $ids  = implode(',', array_column($allEmployees, 'id'));
    $rows = $db->query("SELECT * FROM escalas WHERE funcionario_id IN ($ids) AND semana_inicio = '$weekStart'")->fetchAll();
    foreach ($rows as $r) {
        $allShifts[$r['funcionario_id']][$r['dia_semana']] = $r;
    }
}

$diasNomes  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$diasFull   = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$weekDays   = [1,2,3,4,5,6,0]; // seg → dom

$weekStartDt = new DateTime($weekStart);
$dates = [];
foreach ($weekDays as $d) {
    $dt = clone $weekStartDt;
    $dt->modify('+' . ($d === 0 ? 6 : $d - 1) . ' days');
    $dates[$d] = $dt->format('d/m');
}

function buildWeekOptions($current) {
    $o = '';
    for ($i = -2; $i <= 6; $i++) {
        $ws  = getWeekStart($i);
        $lbl = getWeekLabel($ws);
        $pfx = $i === 0 ? 'Esta semana' : ($i < 0 ? 'Semana passada' : "Semana +{$i}");
        $sel = $ws === $current ? 'selected' : '';
        $o  .= "<option value=\"$ws\" $sel>$pfx · $lbl</option>";
    }
    return $o;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>McEscala – Admin</title>
<link rel="stylesheet" href="assets/style.css">
<style>
body { background:#111; min-height:100vh; }

/* Header */
.admin-header {
    background:var(--mc-yellow); padding:14px 20px;
    display:flex; align-items:center; gap:12px;
    position:sticky; top:0; z-index:200;
    box-shadow:0 2px 12px rgba(0,0,0,.4);
}
.hlogo { font-size:30px; font-weight:900; font-style:italic; color:var(--mc-dark); line-height:1; }
.htitle { font-size:15px; font-weight:700; color:var(--mc-dark); }
.hsub   { font-size:11px; color:rgba(39,37,31,.6); }
.hspace { flex:1; }
.hout {
    background:var(--mc-red); color:#fff; border:none; border-radius:8px;
    padding:8px 16px; font-size:13px; font-weight:600;
    text-decoration:none; white-space:nowrap; font-family:inherit;
}

/* Tabs */
.admin-tabs { display:flex; background:#1a1a1a; border-bottom:1px solid #2a2a2a; }
.tab-btn {
    flex:1; padding:14px 6px; background:none; border:none; color:#666;
    font-size:12px; font-weight:600; cursor:pointer; font-family:inherit;
    border-bottom:3px solid transparent; text-decoration:none;
    display:block; text-align:center; transition:all .2s;
}
.tab-btn.active { color:var(--mc-yellow); border-bottom-color:var(--mc-yellow); }

/* Body */
.admin-body { max-width:100%; padding:16px; }

/* Card */
.card {
    background:#1e1e1e; border-radius:var(--radius);
    padding:16px; margin-bottom:14px; border:1px solid #2a2a2a;
}
.card-title {
    font-size:12px; font-weight:700; color:var(--mc-yellow);
    text-transform:uppercase; letter-spacing:.8px; margin-bottom:14px;
}

/* Dark inputs */
.d-input,.d-sel {
    width:100%; padding:10px 13px;
    background:#2a2a2a; border:1.5px solid #3a3a3a;
    border-radius:8px; color:#fff; font-size:14px;
    font-family:inherit; outline:none;
    transition:border-color .2s; margin-bottom:10px;
}
.d-input:focus,.d-sel:focus { border-color:var(--mc-yellow); }
.d-sel option { background:#2a2a2a; }
.d-lbl {
    display:block; font-size:11px; font-weight:700; color:#666;
    text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px;
}
.d-group { margin-bottom:12px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

/* Week bar */
.week-bar { display:flex; align-items:center; gap:8px; margin-bottom:16px; }
.wbtn {
    display:flex; align-items:center; justify-content:center;
    width:36px; height:36px;
    background:#252525; border:1px solid #3a3a3a; border-radius:8px;
    color:var(--mc-yellow); font-size:20px; text-decoration:none;
    font-weight:700; flex-shrink:0; transition:background .15s;
}
.wbtn:hover { background:#333; }
.week-bar select { flex:1; margin:0; }

/* ========== GRADE ========== */
.grade-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; margin:0 -4px; }

.grade-table {
    border-collapse:collapse;
    width:100%;
    min-width:520px;
}
.grade-table th {
    background:#252525; color:#888;
    font-size:11px; font-weight:700; text-transform:uppercase;
    letter-spacing:.5px; padding:8px 6px; text-align:center;
    border-bottom:2px solid #333;
    white-space:nowrap;
}
.grade-table th.th-nome { text-align:left; padding-left:10px; min-width:120px; }
.grade-table th .dia-date { display:block; font-size:10px; color:#555; font-weight:400; letter-spacing:0; }

.grade-table td {
    padding:5px 4px; vertical-align:middle;
    border-bottom:1px solid #222;
}
.grade-table td.td-nome {
    padding:8px 10px; white-space:nowrap;
}
.emp-nome-cell { font-size:13px; font-weight:600; color:#fff; }
.emp-cargo-cell { font-size:11px; color:#555; margin-top:1px; }

/* Célula de dia */
.cell {
    display:flex; flex-direction:column;
    align-items:center; gap:3px;
    min-width:72px;
}

/* Botão de estado */
.state-btn {
    width:100%; padding:7px 4px;
    border:none; border-radius:7px;
    font-size:11px; font-weight:700; cursor:pointer;
    font-family:inherit; text-align:center; line-height:1.2;
    transition:all .15s;
}
.state-none     { background:#2a2a2a; color:#555; }
.state-none:hover { background:#333; color:#888; }
.state-trabalha { background:#3a2a00; color:var(--mc-yellow); }
.state-folga    { background:#1a3a1a; color:#4ade80; }

/* Input de horas (aparece quando trabalha) */
.horas-input {
    width:100%; padding:4px 5px;
    background:#1a1a1a; border:1px solid #3a3a3a;
    border-radius:5px; color:#ddd; font-size:11px;
    font-family:inherit; outline:none; text-align:center;
    display:none;
}
.horas-input:focus { border-color:var(--mc-yellow); }

/* Ações rápidas */
.quick-actions {
    display:flex; gap:6px; flex-wrap:wrap;
    margin-bottom:14px; padding-bottom:14px;
    border-bottom:1px solid #2a2a2a;
}
.qbtn {
    padding:7px 13px; border:1px solid #3a3a3a;
    border-radius:7px; background:#252525; color:#888;
    font-size:12px; font-weight:600; cursor:pointer;
    font-family:inherit; transition:all .15s; white-space:nowrap;
}
.qbtn:hover { background:#333; color:#ccc; }
.qbtn-yellow { border-color:#4a3a00; color:var(--mc-yellow); background:#1e1800; }
.qbtn-green  { border-color:#1a3a1a; color:#4ade80; background:#0e1e0e; }

/* Modal de horário */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.75); z-index:500;
    align-items:center; justify-content:center;
    padding:20px;
}
.modal-overlay.open { display:flex; }
.modal {
    background:#1e1e1e; border:1px solid #3a3a3a;
    border-radius:16px; padding:24px; width:100%;
    max-width:320px; box-shadow:0 20px 60px rgba(0,0,0,.5);
}
.modal h3 { color:var(--mc-yellow); font-size:15px; font-weight:700; margin-bottom:16px; }
.modal-row { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.modal-row label { color:#888; font-size:12px; font-weight:600; min-width:60px; text-transform:uppercase; letter-spacing:.4px; }
.modal-row input[type=time] {
    flex:1; padding:10px; background:#2a2a2a;
    border:1.5px solid #3a3a3a; border-radius:8px;
    color:#fff; font-size:15px; font-family:inherit; outline:none;
}
.modal-row input:focus { border-color:var(--mc-yellow); }
.modal-btns { display:flex; gap:8px; margin-top:6px; }
.modal-btns button { flex:1; padding:11px; border:none; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; }
.mbtn-ok  { background:var(--mc-yellow); color:var(--mc-dark); }
.mbtn-cancel { background:#2a2a2a; color:#888; }

/* Funcionários */
.emp-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #252525; }
.emp-row:last-child { border-bottom:none; }
.emp-avatar { width:40px; height:40px; border-radius:50%; background:var(--mc-yellow); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:var(--mc-dark); flex-shrink:0; }
.emp-details { flex:1; min-width:0; }
.emp-nome-l { color:#fff; font-size:14px; font-weight:600; }
.emp-sub-l  { color:#666; font-size:12px; margin-top:2px; }
.btn-del { background:#2a1515; border:1px solid #4a2020; border-radius:6px; padding:6px 12px; color:#e74c3c; cursor:pointer; font-size:13px; font-family:inherit; }
.btn-del:hover { background:#3a1a1a; }

@media (max-width:400px) { .form-row { grid-template-columns:1fr; } }
</style>
</head>
<body>

<div class="admin-header">
    <div class="hlogo">M</div>
    <div><div class="htitle">McEscala Admin</div><div class="hsub">Olá, <?= sanitize($admin['nome']) ?></div></div>
    <div class="hspace"></div>
    <a href="logout.php" class="hout">Sair</a>
</div>

<div class="admin-tabs">
    <a href="?tab=escalas"      class="tab-btn <?= $tab==='escalas'      ? 'active':'' ?>">📅 Escalas</a>
    <a href="?tab=funcionarios" class="tab-btn <?= $tab==='funcionarios' ? 'active':'' ?>">👥 Equipe</a>
    <a href="?tab=cadastro"     class="tab-btn <?= $tab==='cadastro'     ? 'active':'' ?>">➕ Cadastrar</a>
</div>

<div class="admin-body">

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= sanitize($msg) ?></div>
<?php endif; ?>

<!-- ===== ESCALAS ===== -->
<?php if ($tab === 'escalas'): ?>
<div class="card">
    <div class="card-title">📅 Grade de Escalas da Semana</div>

    <!-- Navegação de semana -->
    <div class="week-bar">
        <a href="?tab=escalas&semana=<?= $weekOffset-1 ?>" class="wbtn">‹</a>
        <form method="GET" style="flex:1;display:flex;gap:8px;">
            <input type="hidden" name="tab" value="escalas">
            <select class="d-sel" name="semana" onchange="this.form.submit()" style="margin:0">
                <?= buildWeekOptions($weekStart) ?>
            </select>
        </form>
        <a href="?tab=escalas&semana=<?= $weekOffset+1 ?>" class="wbtn">›</a>
    </div>

    <!-- Ações rápidas -->
    <div class="quick-actions">
        <span style="color:#555;font-size:12px;align-self:center;">Marcar todos:</span>
        <button type="button" class="qbtn qbtn-yellow" onclick="marcarTodos('trabalha')">⚡ Todos trabalham</button>
        <button type="button" class="qbtn qbtn-green"  onclick="marcarTodos('folga')">🌿 Todos de folga</button>
        <button type="button" class="qbtn"              onclick="marcarTodos('none')">✕ Limpar tudo</button>
        <button type="button" class="qbtn qbtn-yellow" onclick="abrirHoraGlobal()">🕐 Horário padrão</button>
    </div>

    <?php if (empty($allEmployees)): ?>
        <p style="color:#555;font-size:13px;text-align:center;padding:20px 0;">Nenhum funcionário cadastrado.</p>
    <?php else: ?>
    <form method="POST" action="admin.php?tab=escalas&semana=<?= $weekOffset ?>" id="grade-form">
        <input type="hidden" name="action" value="salvar_grade">
        <input type="hidden" name="semana_inicio" value="<?= $weekStart ?>">

        <div class="grade-wrap">
        <table class="grade-table">
            <thead>
                <tr>
                    <th class="th-nome">Funcionário</th>
                    <?php foreach ($weekDays as $d): ?>
                    <th>
                        <?= $diasNomes[$d] ?>
                        <span class="dia-date"><?= $dates[$d] ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allEmployees as $emp):
                $fid = $emp['id'];
            ?>
            <tr>
                <td class="td-nome">
                    <div class="emp-nome-cell"><?= sanitize(explode(' ', $emp['nome'])[0]) ?> <?= sanitize(explode(' ', $emp['nome'])[1] ?? '') ?></div>
                    <div class="emp-cargo-cell"><?= sanitize($emp['cargo']) ?></div>
                </td>
                <?php foreach ($weekDays as $d):
                    $reg  = $allShifts[$fid][$d] ?? null;
                    $isFolga = !empty($reg['folga']);
                    $tipo = !$reg ? 'none' : ($isFolga ? 'folga' : 'trabalha');
                    $ent  = ($reg && !$isFolga && !empty($reg['entrada'])) ? $reg['entrada'] : '08:00';
                    $sai  = ($reg && !$isFolga && !empty($reg['saida']))   ? $reg['saida']   : '16:00';
                    $hval = "{$ent}-{$sai}";
                    $labelMap = ['none'=>'—','trabalha'=>'Trabalha','folga'=>'Folga 🌿'];
                ?>
                <td>
                    <div class="cell">
                        <!-- hidden inputs -->
                        <input type="hidden" name="celula[<?= $fid ?>][<?= $d ?>]" id="cel-<?= $fid ?>-<?= $d ?>" value="<?= $tipo ?>">
                        <input type="hidden" name="horas[<?= $fid ?>][<?= $d ?>]"  id="hor-<?= $fid ?>-<?= $d ?>" value="<?= $hval ?>">

                        <!-- Botão estado (clique alterna) -->
                        <button type="button"
                            class="state-btn state-<?= $tipo ?>"
                            id="btn-<?= $fid ?>-<?= $d ?>"
                            onclick="ciclar(<?= $fid ?>, <?= $d ?>)"
                            ondblclick="abrirModal(<?= $fid ?>, <?= $d ?>)"
                            title="Clique p/ alternar · 2x para definir horário">
                            <?= $labelMap[$tipo] ?>
                        </button>

                        <!-- Mostra o horário abaixo do botão quando trabalha -->
                        <span class="horas-disp" id="hdisp-<?= $fid ?>-<?= $d ?>"
                            style="font-size:10px;color:#888;<?= $tipo!=='trabalha'?'display:none':'' ?>">
                            <?= $ent ?>–<?= $sai ?>
                        </span>
                    </div>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Legenda -->
        <div style="display:flex;gap:14px;margin:14px 0;flex-wrap:wrap;">
            <span style="font-size:12px;color:#555;">💡 Clique para alternar · Duplo clique para editar horário</span>
        </div>

        <button type="submit" class="btn btn-yellow">💾 Salvar Escala Completa</button>
    </form>
    <?php endif; ?>
</div>

<!-- ===== FUNCIONÁRIOS ===== -->
<?php elseif ($tab === 'funcionarios'): ?>
<div class="card">
    <div class="card-title">Equipe — <?= count($allEmployees) ?> cadastrado(s)</div>
    <?php foreach ($allEmployees as $e): ?>
    <div class="emp-row">
        <div class="emp-avatar"><?= getInitials($e['nome']) ?></div>
        <div class="emp-details">
            <div class="emp-nome-l"><?= sanitize($e['nome']) ?></div>
            <div class="emp-sub-l">
                🪪 <?= sanitize($e['cracha']) ?> &nbsp;
                <span class="cargo-tag <?= getCargoClass($e['cargo']) ?>"><?= sanitize($e['cargo']) ?></span>
                <?php if ($e['senha_hash']): ?>&nbsp;<span style="color:#4ade80;font-size:11px;">🔒 admin</span><?php endif; ?>
            </div>
        </div>
        <?php if ($e['id'] !== $admin['id']): ?>
        <form method="POST" onsubmit="return confirm('Remover <?= sanitize($e['nome']) ?>?')">
            <input type="hidden" name="action" value="deletar">
            <input type="hidden" name="id" value="<?= $e['id'] ?>">
            <button type="submit" class="btn-del">Remover</button>
        </form>
        <?php else: ?>
            <span style="color:#444;font-size:12px;">você</span>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== CADASTRO ===== -->
<?php elseif ($tab === 'cadastro'): ?>
<div class="card">
    <div class="card-title">Cadastrar Novo Funcionário</div>
    <form method="POST" action="admin.php?tab=cadastro" autocomplete="off">
        <input type="hidden" name="action" value="cadastrar">
        <div class="d-group">
            <label class="d-lbl">Nome Completo</label>
            <input type="text" name="nome" class="d-input" placeholder="Ex: Maria da Silva" required value="<?= sanitize($_POST['nome'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="d-group">
                <label class="d-lbl">Crachá</label>
                <input type="tel" name="cracha" class="d-input" placeholder="60153612" maxlength="12" required value="<?= sanitize($_POST['cracha'] ?? '') ?>">
            </div>
            <div class="d-group">
                <label class="d-lbl">Cargo</label>
                <select name="cargo" class="d-sel">
                    <?php foreach (['Atendente','Supervisor','Gerente de Plantão','Administração','Manutenção'] as $c): ?>
                        <option value="<?= $c ?>" <?= ($_POST['cargo']??'')===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="d-group">
            <label class="d-lbl">Senha (obrigatória para Gerente de Plantão e Administração)</label>
            <input type="password" name="senha" class="d-input" placeholder="Deixe vazio para não ter acesso admin">
        </div>
        <button type="submit" class="btn btn-yellow">Cadastrar Funcionário</button>
    </form>
</div>
<?php endif; ?>

</div>

<!-- ===== MODAL HORÁRIO ===== -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <h3 id="modal-title">Definir Horário</h3>
        <div class="modal-row">
            <label>Entrada</label>
            <input type="time" id="modal-entrada" value="08:00">
        </div>
        <div class="modal-row">
            <label>Saída</label>
            <input type="time" id="modal-saida" value="16:00">
        </div>
        <div class="modal-btns">
            <button class="mbtn-ok"     onclick="confirmarModal()">Confirmar</button>
            <button class="mbtn-cancel" onclick="fecharModal()">Cancelar</button>
        </div>
    </div>
</div>

<!-- ===== MODAL HORÁRIO GLOBAL ===== -->
<div class="modal-overlay" id="modal-global">
    <div class="modal">
        <h3>Horário padrão para todos</h3>
        <p style="color:#888;font-size:12px;margin-bottom:14px;">Aplica este horário em todas as células marcadas como "Trabalha"</p>
        <div class="modal-row">
            <label>Entrada</label>
            <input type="time" id="global-entrada" value="08:00">
        </div>
        <div class="modal-row">
            <label>Saída</label>
            <input type="time" id="global-saida" value="16:00">
        </div>
        <div class="modal-btns">
            <button class="mbtn-ok"     onclick="aplicarHoraGlobal()">Aplicar a todos</button>
            <button class="mbtn-cancel" onclick="fecharModalGlobal()">Cancelar</button>
        </div>
    </div>
</div>

<script>
const LABELS = { none:'—', trabalha:'Trabalha', folga:'Folga 🌿' };
const CLASSES = { none:'state-none', trabalha:'state-trabalha', folga:'state-folga' };
const CYCLE   = { none:'trabalha', trabalha:'folga', folga:'none' };

let modalFid = null, modalDia = null;

function ciclar(fid, dia) {
    const inp   = document.getElementById('cel-' + fid + '-' + dia);
    const atual = inp.value;
    const prox  = CYCLE[atual];
    setEstado(fid, dia, prox);
}

function setEstado(fid, dia, estado, ent, sai) {
    const inp  = document.getElementById('cel-' + fid + '-' + dia);
    const btn  = document.getElementById('btn-' + fid + '-' + dia);
    const hdisp = document.getElementById('hdisp-' + fid + '-' + dia);

    inp.value = estado;
    btn.className = 'state-btn ' + CLASSES[estado];
    btn.textContent = LABELS[estado];

    if (estado === 'trabalha') {
        const horInput = document.getElementById('hor-' + fid + '-' + dia);
        const parts = horInput.value.split('-');
        const e = ent || parts[0] || '08:00';
        const s = sai || parts[1] || '16:00';
        horInput.value = e + '-' + s;
        hdisp.textContent = e + '–' + s;
        hdisp.style.display = '';
    } else {
        hdisp.style.display = 'none';
    }
}

function abrirModal(fid, dia) {
    modalFid = fid; modalDia = dia;
    const hor = document.getElementById('hor-' + fid + '-' + dia).value;
    const parts = hor.split('-');
    document.getElementById('modal-entrada').value = parts[0] || '08:00';
    document.getElementById('modal-saida').value   = parts[1] || '16:00';
    // título amigável
    const nomeEl = document.querySelector('tr td.td-nome .emp-nome-cell');
    document.getElementById('modal-title').textContent = 'Horário';
    document.getElementById('modal').classList.add('open');
    document.getElementById('modal-entrada').focus();
}

function confirmarModal() {
    const ent = document.getElementById('modal-entrada').value;
    const sai = document.getElementById('modal-saida').value;
    if (modalFid !== null) {
        setEstado(modalFid, modalDia, 'trabalha', ent, sai);
    }
    fecharModal();
}

function fecharModal() {
    document.getElementById('modal').classList.remove('open');
    modalFid = null; modalDia = null;
}

function abrirHoraGlobal() {
    document.getElementById('modal-global').classList.add('open');
}
function fecharModalGlobal() {
    document.getElementById('modal-global').classList.remove('open');
}
function aplicarHoraGlobal() {
    const ent = document.getElementById('global-entrada').value;
    const sai = document.getElementById('global-saida').value;
    document.querySelectorAll('[id^="cel-"]').forEach(el => {
        if (el.value === 'trabalha') {
            const parts = el.id.split('-');
            const fid = parts[1], dia = parts[2];
            setEstado(fid, dia, 'trabalha', ent, sai);
        }
    });
    fecharModalGlobal();
}

function marcarTodos(estado) {
    document.querySelectorAll('[id^="cel-"]').forEach(el => {
        const parts = el.id.split('-');
        setEstado(parts[1], parts[2], estado);
    });
}

// Fechar modal ao clicar fora
document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
document.getElementById('modal-global').addEventListener('click', function(e) {
    if (e.target === this) fecharModalGlobal();
});
</script>

</body>
</html>
