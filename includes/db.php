<?php
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . __DIR__ . '/../data/mcescala.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDB($db);
    }
    return $db;
}

function initDB($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS funcionarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            cracha TEXT NOT NULL UNIQUE,
            cargo TEXT NOT NULL DEFAULT 'Atendente',
            senha_hash TEXT,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS escalas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            funcionario_id INTEGER NOT NULL,
            semana_inicio DATE NOT NULL,
            dia_semana INTEGER NOT NULL,
            folga INTEGER NOT NULL DEFAULT 0,
            entrada TIME,
            saida TIME,
            FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
            UNIQUE(funcionario_id, semana_inicio, dia_semana)
        );
    ");

    // Seed: só cria o admin se o banco estiver completamente vazio
    $count = $db->query("SELECT COUNT(*) as c FROM funcionarios")->fetch()['c'];
    if ($count == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT OR IGNORE INTO funcionarios (nome, cracha, cargo, senha_hash) VALUES (?,?,?,?)");
        $stmt->execute(['Gerente Geral', '00000001', 'Administração', $hash]);
    }
}

// Garante que a pasta data existe
if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}
