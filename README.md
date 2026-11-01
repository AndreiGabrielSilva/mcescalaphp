# McEscala 🍔 – Sistema de Escalas McDonald's

Sistema PHP para gerenciamento de escalas de funcionários, no estilo McDonald's.

## Requisitos

- PHP 7.4+ (ou 8.x)
- Extensão PDO + SQLite (vem habilitada por padrão na maioria dos servidores)

## Instalação

1. **Suba os arquivos** para seu servidor (ex: pasta `public_html/mcescala/` ou `htdocs/mcescala/`)

2. **Crie a pasta `data/`** na raiz do projeto com permissão de escrita:
   ```bash
   mkdir data
   chmod 755 data
   ```
   O banco de dados SQLite (`mcescala.db`) será criado automaticamente na primeira visita.

3. Acesse `index.php` no navegador. Pronto!

## Estrutura de arquivos

```
mcescala/
├── index.php          ← Login do funcionário (por crachá)
├── escala.php         ← Tela com a escala do funcionário
├── admin_login.php    ← Login do gerente
├── admin.php          ← Painel admin (escalas, equipe, cadastro)
├── logout.php         ← Encerra sessão
├── assets/
│   └── style.css      ← CSS global
├── includes/
│   ├── db.php         ← Banco de dados SQLite + seed inicial
│   └── helpers.php    ← Funções auxiliares e autenticação
└── data/              ← (criar com permissão de escrita)
    └── mcescala.db    ← Banco gerado automaticamente
```

## Usuários padrão (seed inicial)

| Nome           | Crachá   | Cargo     | Senha    |
|----------------|----------|-----------|----------|
| Gerente Geral  | 00000001 | Gerente   | admin123 |
| João da Silva  | 60153612 | Atendente | —        |
| Ana Costa      | 60153613 | Caixa     | —        |
| Pedro Souza    | 60153614 | Cozinha   | —        |

> **Recomendado:** Troque a senha do gerente padrão assim que possível!

## Como usar

### Funcionário
1. Acesse `index.php`
2. Digite o número do crachá
3. Veja sua escala da semana (pode navegar para semanas anteriores/próximas)

### Gerente
1. Clique em "Acesso Gerente / Admin"
2. Entre com crachá + senha
3. **Aba Escalas**: Selecione funcionário + semana → adicione dias com horários → Salvar
4. **Aba Equipe**: Veja e remova funcionários
5. **Aba Cadastrar**: Registre novos funcionários

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Sessões PHP nativas
- Inputs sanitizados com `htmlspecialchars()`
- Acesso admin restrito a cargo Gerente + senha
- Para produção: configure HTTPS e mova a pasta `data/` para fora do `public_html`

## Proteção da pasta data/ (Apache)

Crie um arquivo `data/.htaccess`:
```
Deny from all
```
