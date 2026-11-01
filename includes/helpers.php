<?php
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedInEmployee() {
    startSession();
    return isset($_SESSION['employee_id']);
}

function isLoggedInAdmin() {
    startSession();
    return isset($_SESSION['admin_id']);
}

function getCurrentEmployee() {
    if (!isLoggedInEmployee()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = ?");
    $stmt->execute([$_SESSION['employee_id']]);
    return $stmt->fetch();
}

function getCurrentAdmin() {
    if (!isLoggedInAdmin()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

function requireEmployee() {
    if (!isLoggedInEmployee()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    if (!isLoggedInAdmin()) {
        header('Location: admin_login.php');
        exit;
    }
    $admin = getCurrentAdmin();
    if (!$admin || !in_array($admin['cargo'], ['Gerente de Plantão', 'Administração'])) {
        header('Location: admin_login.php');
        exit;
    }
}

// format('w'): 0=Dom, 1=Seg, 2=Ter, 3=Qua, 4=Qui, 5=Sex, 6=Sáb
// Semana começa na Segunda (dia 1)
function getWeekStart($offset = 0) {
    $now = new DateTime();
    $dow = (int)$now->format('w'); // 0=dom, 1=seg...6=sab
    // Quantos dias voltar para chegar na segunda
    $daysBack = ($dow === 0) ? 6 : $dow - 1;
    $now->modify('-' . $daysBack . ' days');
    if ($offset !== 0) {
        $now->modify(($offset > 0 ? '+' : '') . $offset . ' weeks');
    }
    return $now->format('Y-m-d');
}

function getWeekLabel($weekStart) {
    $start = new DateTime($weekStart);
    $end   = clone $start;
    $end->modify('+6 days');
    return $start->format('d/m') . ' – ' . $end->format('d/m/Y');
}

// Retorna o dia da semana atual: 0=Dom, 1=Seg...6=Sáb
function getTodayDow() {
    return (int)(new DateTime())->format('w');
}

function getDayName($dayNum) {
    $days = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
    return $days[$dayNum] ?? '';
}

function getCargoClass($cargo) {
    $map = [
        'Atendente'          => 'cargo-atend',
        'Supervisor'         => 'cargo-sup',
        'Gerente de Plantão' => 'cargo-ger',
        'Administração'      => 'cargo-adm',
        'Manutenção'         => 'cargo-man',
    ];
    return $map[$cargo] ?? 'cargo-atend';
}

function isCargoAdmin($cargo) {
    return in_array($cargo, ['Gerente de Plantão', 'Administração']);
}

function getInitials($nome) {
    $parts = explode(' ', trim($nome));
    $ini   = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini;
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
