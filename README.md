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
Não esqueça das atualizações de bancos posteriores
4. Abra `http://localhost/ClinicaVeterinaria`

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
