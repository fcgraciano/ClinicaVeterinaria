# 🐾 Patas & Cia — Sistema da Clínica Veterinária

Projeto prático em PHP + Bootstrap + MySQL.

## Como rodar

Baixando o projeto na escola
abrir CMD
cd c:/xampp/htdocs
git clone https://github.com/fcgraciano/ClinicaVeterinaria
cd ClinicaVeterinaria
code .

depois que o projeto já está baixado na maquina

git pull

1. Coloque a pasta em `C:\xampp\htdocs\ClinicaVeterinaria`
2. Ligue o **Apache** e o **MySQL** no XAMPP
3. No phpMyAdmin, rode `banco/00_criar_banco.sql`
4. Rode também `banco/01_autenticacao.sql` no phpMyAdmin para criar as tabelas de usuários e grupos.
5. Abra `http://localhost/ClinicaVeterinaria/Usuarios/PrimeiroAcesso.php` e crie o primeiro administrador. A página bloqueia novos cadastros depois que a primeira conta existe.
6. Entre em `http://localhost/ClinicaVeterinaria` com o e-mail e senha escolhidos.

## Login e permissões

O sistema usa PHP procedural, sessões e `mysqli` (sem PDO e sem orientação a objetos). As senhas são armazenadas com `password_hash`. A tabela `usuario_grupos` permite associar vários grupos a cada usuário. As roles disponíveis são `admin`, `recepcao`, `veterinario`, `financeiro` e `estoque`; o administrador tem acesso a todos os módulos.

Páginas novas de módulo devem chamar `exigir_role(array('role'))` antes de qualquer HTML, depois de incluir `includes/autenticacao.php`. Para exigir apenas uma sessão autenticada use `exigir_login()`.

## Pastas

```
ClinicaVeterinaria/
├── includes/
│   ├── conexao.php     ← conexão com o banco
│   ├── cabecalho.php   ← topo da página + menu
│   └── rodape.php      ← fim da página
├── css/estilo.css
├── banco/              ← arquivos .sql (um por grupo)
└── inicio/             ← página inicial
 
```

## Como criar uma página nova

Crie o arquivo dentro da pasta do seu grupo, por exemplo `agendamento/novo.php`:

```php
<?php include '../includes/cabecalho.php'; ?>

<h1>Novo agendamento</h1>

<!-- seu conteúdo aqui -->

<?php include '../includes/rodape.php'; ?>
```

Vai usar o banco? Coloque também a conexão **na primeira linha**:

```php
<?php include '../includes/conexao.php'; ?>
```


## Regras

1. Mexa **só na pasta do seu grupo** e no seu arquivo `.sql` da pasta `banco`.
2. Não altere a pasta `includes`. Precisa mudar algo? Fale com o professor.
3. Use a pasta `exemplo` como modelo.


**Criar a branch da task** 

```bash
git checkout main
git pull
git checkout -b grupo-agendamento
git push -u origin grupo-agendamento
```

**Fazer uma tarefa** (cada aluno):

```bash
git checkout grupo-agendamento
git pull
git checkout -b agendamento-listar
# ... programa ...
git add .
git commit -m "Agendamento: listar consultas"
git push -u origin agendamento-listar
```

**Juntar a tarefa na branch do grupo:**

```bash
git checkout grupo-agendamento
git pull
git merge agendamento-listar
git push
```

**No final**, o grupo abre um Pull Request de `grupo-agendamento` para `main`.
