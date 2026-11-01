<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

if (isLoggedInAdmin()) { header('Location: admin.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cracha = trim($_POST['cracha'] ?? '');
    $senha  = $_POST['senha'] ?? '';

    if (empty($cracha) || empty($senha)) {
        $error = 'Preencha o crachá e a senha.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE cracha = ? AND cargo IN ('Gerente de Plantão','Administração') AND ativo = 1");
        $stmt->execute([$cracha]);
        $emp = $stmt->fetch();

        if ($emp && $emp['senha_hash'] && password_verify($senha, $emp['senha_hash'])) {
            $_SESSION['admin_id'] = $emp['id'];
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Crachá ou senha incorretos. Somente Gerente de Plantão ou Administração têm acesso.';
        }
    }
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
        body { background: var(--mc-dark); }

        .bg-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow: hidden;
        }
        .bg-wrapper::before {
            content: '';
            position: fixed;
            bottom: -60px; left: -60px;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,199,44,0.06);
        }

        .logo-block { text-align: center; margin-bottom: 28px; z-index: 1; }
        .mc-m {
            font-size: 72px;
            font-weight: 900;
            font-style: italic;
            color: var(--mc-yellow);
            line-height: 1;
            text-shadow: 4px 4px 0 rgba(0,0,0,0.3);
            display: block;
        }
        .mc-sub {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .login-card {
            background: #1e1e1e;
            border-radius: 20px;
            padding: 28px 24px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.4);
            border: 1px solid #333;
            z-index: 1;
        }
        .login-card h2 {
            font-size: 18px;
            font-weight: 800;
            color: white;
            margin-bottom: 4px;
        }
        .login-card .subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 22px;
        }

        .dark-input-group { margin-bottom: 14px; }
        .dark-input-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 6px;
        }
        .dark-input-group input {
            width: 100%;
            padding: 13px 16px;
            background: #2a2a2a;
            border: 1.5px solid #444;
            border-radius: 10px;
            font-size: 16px;
            color: white;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        .dark-input-group input:focus { border-color: var(--mc-yellow); }
        .dark-input-group input.cracha-input {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 4px;
            text-align: center;
            color: var(--mc-yellow);
        }

        .divider {
            border: none;
            border-top: 1px solid #333;
            margin: 20px 0;
        }
        .back-link {
            text-align: center;
        }
        .back-link a {
            font-size: 13px;
            color: #888;
            text-decoration: none;
        }
        .back-link a:hover { color: var(--mc-yellow); }
    </style>
</head>
<body>
<div class="bg-wrapper">
    <div class="logo-block">
        <span class="mc-m">M</span>
        <span class="mc-sub">Acesso Administrativo</span>
    </div>

    <div class="login-card">
        <h2>🔒 Painel do Gerente</h2>
        <p class="subtitle">Somente gerentes têm acesso a esta área</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="dark-input-group">
                <label>Crachá</label>
                <input type="tel" name="cracha" class="cracha-input" placeholder="00000000" maxlength="12" autofocus required value="<?= sanitize($_POST['cracha'] ?? '') ?>">
            </div>
            <div class="dark-input-group">
                <label>Senha</label>
                <input type="password" name="senha" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-yellow">Entrar como Gerente</button>
        </form>

        <hr class="divider">
        <div class="back-link">
            <a href="index.php">← Voltar para o Login</a>
        </div>
    </div>
</div>
</body>
</html>
