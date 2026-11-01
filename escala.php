<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
startSession();
requireEmployee();

$emp = getCurrentEmployee();
$db  = getDB();

$offset    = (int)($_GET['semana'] ?? 0);
$weekStart = getWeekStart($offset);
$weekLabel = getWeekLabel($weekStart);

$stmt = $db->prepare("SELECT dia_semana, folga, entrada, saida FROM escalas WHERE funcionario_id = ? AND semana_inicio = ? ORDER BY dia_semana");
$stmt->execute([$emp['id'], $weekStart]);

$shiftMap = [];
foreach ($stmt->fetchAll() as $s) {
    $shiftMap[$s['dia_semana']] = $s;
}

$weekDays    = [1,2,3,4,5,6,0]; // seg→dom
$weekStartDt = new DateTime($weekStart);
$today = getTodayDow(); // 0=dom, 1=seg, 2=ter, 3=qua, 4=qui, 5=sex, 6=sab

function getDateForDay($weekStartDt, $dayNum) {
    $d = clone $weekStartDt;
    $d->modify('+' . ($dayNum === 0 ? 6 : $dayNum - 1) . ' days');
    return $d;
}

$initials  = getInitials($emp['nome']);
$cargoClass = getCargoClass($emp['cargo']);

// Conta dias trabalhando e de folga na semana
$totalTrabalha = 0; $totalFolga = 0;
foreach ($shiftMap as $s) { !empty($s['folga']) ? $totalFolga++ : $totalTrabalha++; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Minha Escala – McEscala</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background:#f0ede8; }

        .emp-header {
            background:var(--mc-red);
            position:relative; overflow:hidden;
        }
        .emp-header::before {
            content:''; position:absolute;
            right:-40px; top:-40px; width:200px; height:200px;
            border-radius:50%; background:rgba(255,199,44,.13);
        }
        .emp-header::after {
            content:''; position:absolute;
            left:-60px; bottom:-60px; width:180px; height:180px;
            border-radius:50%; background:rgba(0,0,0,.06);
        }
        .header-inner { padding:20px 20px 24px; position:relative; z-index:1; }
        .header-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .mc-logo { font-size:34px; font-weight:900; font-style:italic; color:var(--mc-yellow); line-height:1; }
        .logout-btn {
            background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3);
            border-radius:8px; padding:7px 14px; color:#fff;
            font-size:13px; text-decoration:none; font-weight:500;
        }

        .emp-profile { display:flex; align-items:center; gap:14px; }
        .emp-avatar-big {
            width:52px; height:52px; border-radius:50%;
            background:var(--mc-yellow); display:flex; align-items:center;
            justify-content:center; font-weight:800; font-size:18px;
            color:var(--mc-dark); flex-shrink:0;
            border:2px solid rgba(255,255,255,.3);
        }
        .welcome-line  { color:rgba(255,255,255,.7); font-size:12px; margin-bottom:1px; }
        .emp-name-big  { color:#fff; font-size:20px; font-weight:800; line-height:1.2; }
        .emp-meta      { display:flex; align-items:center; gap:8px; margin-top:5px; flex-wrap:wrap; }
        .cracha-pill {
            display:inline-flex; gap:5px; align-items:center;
            background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
            border-radius:20px; padding:3px 10px;
            font-size:12px; color:rgba(255,255,255,.85);
        }
        .cracha-pill strong { color:#fff; letter-spacing:1px; }

        /* Resumo da semana */
        .week-summary {
            display:flex; gap:8px; margin-top:14px;
        }
        .summary-pill {
            display:flex; align-items:center; gap:5px;
            border-radius:20px; padding:5px 12px;
            font-size:12px; font-weight:700;
        }
        .summary-work { background:rgba(255,199,44,.2); color:var(--mc-yellow); }
        .summary-off  { background:rgba(74,222,128,.15); color:#4ade80; }

        /* Seção */
        .schedule-section { max-width:520px; margin:0 auto; padding:20px 16px; }

        .week-nav {
            display:flex; align-items:center; justify-content:space-between;
            background:#fff; border-radius:var(--radius);
            padding:12px 16px; margin-bottom:16px;
            box-shadow:0 2px 10px rgba(0,0,0,.07);
        }
        .wn-btn {
            display:flex; align-items:center; justify-content:center;
            width:36px; height:36px; background:#f5f5f5;
            border:1px solid var(--mc-border); border-radius:8px;
            color:var(--mc-red); font-size:20px; text-decoration:none;
            font-weight:700; line-height:1;
        }
        .week-title .label { font-size:11px; color:var(--mc-muted); text-transform:uppercase; letter-spacing:.5px; display:block; text-align:center; }
        .week-title .dates { font-size:15px; font-weight:700; color:var(--mc-dark); display:block; text-align:center; }

        .section-label { font-size:11px; font-weight:700; color:var(--mc-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }

        /* Cards de dia */
        .day-card {
            background:#fff; border-radius:var(--radius);
            padding:14px 16px; margin-bottom:8px;
            display:flex; align-items:center; gap:14px;
            box-shadow:0 2px 8px rgba(0,0,0,.05);
            border-left:4px solid transparent;
        }
        .day-card.is-today   { border-left-color:var(--mc-red); box-shadow:0 4px 16px rgba(218,41,28,.1); }
        .day-card.is-trabalha:not(.is-today) { border-left-color:var(--mc-yellow); }
        .day-card.is-folga:not(.is-today)    { border-left-color:#4ade80; }

        .day-dot { width:10px; height:10px; border-radius:50%; background:var(--mc-border); flex-shrink:0; }
        .day-dot.trabalha { background:var(--mc-yellow); }
        .day-dot.folga    { background:#4ade80; }
        .day-dot.today    { background:var(--mc-red); }

        .day-name-block { min-width:90px; }
        .day-name  { font-size:14px; font-weight:700; color:var(--mc-dark); }
        .day-date  { font-size:11px; color:var(--mc-muted); margin-top:1px; }

        .shift-info { flex:1; }

        /* Pill trabalha */
        .shift-pill {
            display:inline-flex; align-items:center; gap:6px;
            background:#FFF8E7; border:1.5px solid var(--mc-yellow);
            border-radius:20px; padding:6px 14px;
            font-size:13px; font-weight:700; color:#7a5500;
        }
        /* Pill folga */
        .folga-pill {
            display:inline-flex; align-items:center; gap:6px;
            background:#f0fff4; border:1.5px solid #86efac;
            border-radius:20px; padding:6px 14px;
            font-size:13px; font-weight:700; color:#166534;
        }
        /* Pill sem escala */
        .off-pill {
            display:inline-block; background:#f5f5f5;
            border-radius:20px; padding:6px 14px;
            font-size:12px; color:var(--mc-muted);
        }

        .today-badge {
            font-size:10px; font-weight:700;
            background:var(--mc-red); color:#fff;
            border-radius:6px; padding:2px 7px;
            text-transform:uppercase; letter-spacing:.4px; flex-shrink:0;
        }

        .empty-state { text-align:center; padding:40px 20px; color:var(--mc-muted); }
        .empty-state .emoji { font-size:48px; display:block; margin-bottom:12px; }
    </style>
</head>
<body>

<div class="emp-header">
    <div class="header-inner">
        <div class="header-top">
            <div class="mc-logo">M</div>
            <a href="logout.php" class="logout-btn">Sair</a>
        </div>
        <div class="emp-profile">
            <div class="emp-avatar-big"><?= $initials ?></div>
            <div>
                <div class="welcome-line">Seja bem-vindo(a),</div>
                <div class="emp-name-big"><?= sanitize($emp['nome']) ?></div>
                <div class="emp-meta">
                    <span class="cargo-tag <?= $cargoClass ?>"><?= sanitize($emp['cargo']) ?></span>
                    <span class="cracha-pill">🪪 <strong><?= sanitize($emp['cracha']) ?></strong></span>
                </div>
                <?php if ($totalTrabalha > 0 || $totalFolga > 0): ?>
                <div class="week-summary">
                    <?php if ($totalTrabalha > 0): ?>
                    <span class="summary-pill summary-work">⚡ <?= $totalTrabalha ?> dia<?= $totalTrabalha > 1 ? 's' : '' ?> trabalhando</span>
                    <?php endif; ?>
                    <?php if ($totalFolga > 0): ?>
                    <span class="summary-pill summary-off">🌿 <?= $totalFolga ?> folga<?= $totalFolga > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="schedule-section">

    <div class="week-nav">
        <a href="?semana=<?= $offset - 1 ?>" class="wn-btn">‹</a>
        <div class="week-title">
            <span class="label"><?= $offset === 0 ? 'Esta semana' : ($offset < 0 ? 'Semana anterior' : 'Próxima semana') ?></span>
            <span class="dates"><?= $weekLabel ?></span>
        </div>
        <a href="?semana=<?= $offset + 1 ?>" class="wn-btn">›</a>
    </div>

    <div class="section-label">Sua Escala</div>

    <?php if (empty($shiftMap)): ?>
        <div class="empty-state">
            <span class="emoji">📋</span>
            <p>Nenhuma escala cadastrada para esta semana.</p>
        </div>
    <?php else: ?>

    <?php foreach ($weekDays as $dayNum):
        $isToday  = ($today === $dayNum);
        $reg      = $shiftMap[$dayNum] ?? null;
        $isFolga  = $reg && !empty($reg['folga']);
        $isTrabalha = $reg && empty($reg['folga']);
        $dt       = getDateForDay($weekStartDt, $dayNum);

        $cardClass = $isToday ? 'is-today' : ($isFolga ? 'is-folga' : ($isTrabalha ? 'is-trabalha' : ''));
        $dotClass  = $isToday ? 'today' : ($isFolga ? 'folga' : ($isTrabalha ? 'trabalha' : ''));
    ?>
    <div class="day-card <?= $cardClass ?>">
        <div class="day-dot <?= $dotClass ?>"></div>
        <div class="day-name-block">
            <div class="day-name"><?= getDayName($dayNum) ?></div>
            <div class="day-date"><?= $dt->format('d/m') ?></div>
        </div>
        <div class="shift-info">
            <?php if ($isFolga): ?>
                <span class="folga-pill">🌿 Folga</span>
            <?php elseif ($isTrabalha): ?>
                <span class="shift-pill">
                    <?= sanitize($reg['entrada']) ?> → <?= sanitize($reg['saida']) ?>
                </span>
            <?php else: ?>
                <span class="off-pill">Sem escala</span>
            <?php endif; ?>
        </div>
        <?php if ($isToday): ?><span class="today-badge">Hoje</span><?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>
