<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();

// Já logado?
if (isLoggedInEmployee()) { header('Location: escala.php'); exit; }
if (isLoggedInAdmin()) { header('Location: admin.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cracha = trim($_POST['cracha'] ?? '');
    if (empty($cracha)) {
        $error = 'Digite seu número de crachá.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE cracha = ? AND ativo = 1");
        $stmt->execute([$cracha]);
        $emp = $stmt->fetch();
        if ($emp) {
            $_SESSION['employee_id'] = $emp['id'];
            header('Location: escala.php');
            exit;
        } else {
            $error = 'Crachá não encontrado. Fale com o gerente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>McEscala – Login</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: var(--mc-red); }

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
            bottom: -80px; left: -80px;
            width: 320px; height: 320px;
            border-radius: 50%;
            background: rgba(255,199,44,0.12);
            pointer-events: none;
        }
        .bg-wrapper::after {
            content: '';
            position: fixed;
            top: -50px; right: -50px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,199,44,0.08);
            pointer-events: none;
        }

        .logo-block {
            text-align: center;
            margin-bottom: 28px;
            z-index: 1;
        }
        .mc-m {
            font-size: 80px;
            font-weight: 900;
            font-style: italic;
            color: var(--mc-yellow);
            line-height: 1;
            text-shadow: 5px 5px 0 rgba(0,0,0,0.18);
            display: block;
        }
        .mc-sub {
            color: rgba(255,255,255,0.75);
            font-size: 12px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            padding: 28px 24px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.22);
            z-index: 1;
        }
        .login-card h2 {
            font-size: 19px;
            font-weight: 800;
            color: var(--mc-dark);
            margin-bottom: 4px;
        }
        .login-card .subtitle {
            font-size: 13px;
            color: var(--mc-muted);
            margin-bottom: 22px;
        }

        .cracha-preview {
            text-align: center;
            margin-bottom: 18px;
        }
        .cracha-icon {
            width: 56px; height: 56px;
            background: #FFF8E7;
            border: 2px solid var(--mc-yellow);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 6px;
        }
        .cracha-label {
            display: block;
            font-size: 12px;
            color: var(--mc-muted);
        }

        .divider {
            border: none;
            border-top: 1px solid var(--mc-border);
            margin: 20px 0;
        }

        .admin-link {
            text-align: center;
            margin-top: 14px;
        }
        .admin-link a {
            font-size: 13px;
            color: var(--mc-red);
            text-decoration: none;
            font-weight: 500;
        }
        .admin-link a:hover { text-decoration: underline; }

        /* Impede zoom no iOS ao focar input */
        @media (max-width: 480px) {
            .input-group input { font-size: 16px; }
        }
    </style>
</head>
<body>
<div class="bg-wrapper">
    <div class="logo-block">
        <span class="mc-m">M</span>
        <span class="mc-sub">McEscala · Sistema de Turnos</span>
    </div>

    <div class="login-card">
        <h2>Consultar minha escala</h2>
        <p class="subtitle">Digite o número do seu crachá para entrar</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <div class="cracha-preview">
            <div class="cracha-icon">🪪</div>
            <span class="cracha-label">Número do Crachá</span>
        </div>

        <form method="POST" autocomplete="off">
            <div class="input-group">
                <label>Crachá</label>
                <input
                    type="tel"
                    name="cracha"
                    class="cracha-input"
                    placeholder="00000000"
                    maxlength="12"
                    autofocus
                    required
                    value="<?= sanitize($_POST['cracha'] ?? '') ?>"
                />
            </div>
            <button type="submit" class="btn btn-yellow">Entrar</button>
        </form>

        <hr class="divider">

        <div class="admin-link">
            <a href="admin_login.php">🔒 Acesso Gerente / Admin</a>
        </div>
    </div>
</div>
</body>
</html>
