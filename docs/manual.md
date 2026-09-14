# Manual do BodyCare

## 1. Requisitos

O projeto usa PHP simples e SQLite.

- PHP 8 ou superior.
- Extensao `pdo_sqlite` habilitada.
- SQLite opcional para executar comandos no terminal.
- Navegador web.

No Windows, confirme a instalacao com:

```powershell
php -v
php -m | Select-String sqlite
```

Se `pdo_sqlite` nao aparecer, habilite `extension=pdo_sqlite` no `php.ini` e reinicie o servidor.

## 2. Criar o banco

Abra o PowerShell na pasta raiz do projeto e crie a pasta de dados:

```powershell
New-Item -ItemType Directory -Force pages/php/data
```

Com o programa `sqlite3` instalado, execute:

```powershell
sqlite3 pages/php/data/bodycare.sqlite ".read database/bodycare.sql"
```

Sem o comando `sqlite3`, abra `database/bodycare.sql` em uma ferramenta grafica de SQLite e execute todo o arquivo sobre um banco novo chamado `bodycare.sqlite` dentro de `pages/php/data`.

O PHP apenas abre o arquivo existente. Ele nao cria tabelas automaticamente. Para recriar um banco de teste, apague o arquivo e execute o SQL novamente:

```powershell
Remove-Item pages/php/data/bodycare.sqlite
sqlite3 pages/php/data/bodycare.sqlite ".read database/bodycare.sql"
```

Nao execute esse procedimento sobre um banco com dados importantes.

## 3. Iniciar o site

Ainda na pasta raiz, inicie o servidor embutido do PHP:

```powershell
php -S localhost:8000
```

Abra no navegador:

- Login funcional: `http://localhost:8000/pages/login.php`
- Pagina original: `http://localhost:8000/index.php`

`index.php` e `pages/cadastro.php` pertencem ao material original e nao foram alterados nesta etapa. O cadastro original ainda e apenas uma tela visual; para testar o sistema, use os usuarios do SQL ou crie usuarios pelo perfil administrador.

## 4. Usuarios de demonstracao

Todos os usuarios abaixo usam a senha `123456`:

| Identificador | Perfil | Area |
|---|---|---|
| `admin@bodycare.local` | admin | `pages/adm/index.php` |
| `financeiro@bodycare.local` | financeiro | `pages/fin/index.php` |
| `medico@bodycare.local` | medico | `pages/med/index.php` |
| `enfermeiro@bodycare.local` | enfermeiro | `pages/tri/index.php` |
| `recepcao@bodycare.local` | recepcao | `pages/rec/index.php` |
| `cliente@bodycare.local` | cliente | `pages/pac/index.php` |

Use esses dados somente em ambiente de teste. Nao coloque dados reais de pacientes neste banco de demonstracao.

## 5. Roteiro de teste completo

1. Entre como administrador e confirme a lista de usuarios.
2. Crie um novo usuario cliente. O sistema cria tambem o registro em `clientes`.
3. Saia e entre como recepcao.
4. Crie um agendamento escolhendo cliente, medico, especialidade, data, hora e tipo.
5. Tente criar outro agendamento para o mesmo medico e horario. O sistema deve informar que o horario esta indisponivel.
6. Registre a chegada informando o motivo.
7. Saia e entre como enfermeiro. A chegada deve aparecer na fila.
8. Informe dados clinicos, especialidade e nivel. A fila deve mostrar a gravidade por texto e ordenar emergencia antes de urgente, prioritario e eletivo.
9. Saia e entre como medico. O atendimento classificado deve aparecer antes dos demais conforme prioridade e horario.
10. Inicie o atendimento, registre diagnostico, solicite exame e prescreva medicamento.
11. Encaminhe para internacao com leito, motivo e custo, ou registre orientacoes de alta para concluir a consulta.
12. Entre como enfermeiro ou medico em Internacoes, registre evolucao e altere leito/status. Depois da alta, uma nova evolucao deve ser recusada.
13. Entre como financeiro, crie uma cobranca, registre um pagamento parcial e confirme que o status ficou `parcial` e que o saldo continua aberto.
14. Cadastre ou edite convenio e vincule-o a um cliente com numero e vigencia.
15. Entre como cliente. Apenas os agendamentos do cliente autenticado devem aparecer.

## 6. Testar acesso negado

Com uma sessao de recepcao aberta, digite diretamente `http://localhost:8000/pages/adm/index.php`. A resposta deve ser `Acesso negado para este perfil` e nenhum dado administrativo deve ser exibido.

Repita o teste com outros perfis e areas. O cliente tambem deve ser impedido de abrir a area de outro cliente alterando IDs na URL ou em formularios.

## 7. Verificacoes tecnicas

Para conferir a sintaxe PHP de todos os arquivos, use no PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Para listar as tabelas com SQLite:

```powershell
sqlite3 pages/php/data/bodycare.sqlite ".tables"
```

Para conferir usuarios e perfis:

```powershell
sqlite3 -header -column pages/php/data/bodycare.sqlite "SELECT id, nome, identificador, perfil, status FROM usuarios;"
```

Para conferir a fila clinica:

```powershell
sqlite3 -header -column pages/php/data/bodycare.sqlite "SELECT c.id, t.nivel, c.chegada_em FROM chegadas c LEFT JOIN triagens t ON t.chegada_id = c.id ORDER BY CASE t.nivel WHEN 'emergencia' THEN 1 WHEN 'urgente' THEN 2 WHEN 'prioritario' THEN 3 WHEN 'eletivo' THEN 4 ELSE 5 END, c.chegada_em;"
```

Neste ambiente de desenvolvimento, os comandos `php` e `sqlite3` podem nao estar no `PATH`. Nesse caso, instale PHP com PDO SQLite e SQLite, reabra o terminal e repita os comandos. A validacao estatica ainda pode conferir os arquivos, mas a sintaxe e os fluxos web precisam de um ambiente PHP real.

## 8. Erros comuns

- **Banco ausente:** execute novamente `database/bodycare.sql` em `pages/php/data/bodycare.sqlite`.
- **Tabela inexistente:** o arquivo foi criado vazio ou o SQL foi executado parcialmente; recrie o banco de teste.
- **`could not find driver`:** habilite `pdo_sqlite` no PHP.
- **Login invalido:** confirme o identificador, a senha `123456` e se o usuario esta `ativo`.
- **Horario indisponivel:** o mesmo medico ja possui agendamento ativo naquele horario.
- **Acesso negado:** cada perfil possui uma area propria; use o usuario correto.
- **Formulario expirado:** recarregue a pagina para obter um novo token CSRF.

## 9. Backup e dados reais

O banco local e o arquivo `pages/php/data/bodycare.sqlite`. Para um backup simples, pare o servidor e copie o arquivo:

```powershell
Copy-Item pages/php/data/bodycare.sqlite backups/bodycare-$(Get-Date -Format yyyyMMdd-HHmmss).sqlite
```

Crie a pasta `backups` antes do comando, se necessario. Proteja os backups e nunca publique dados de pacientes, senhas ou arquivos SQLite em repositorio publico.

Ainda precisam ser confirmados com uma entrevista valida os campos obrigatorios, especialidades, niveis oficiais de gravidade, regras de convenio, motivos permitidos, politica de senha e permissoes detalhadas do prontuario.
