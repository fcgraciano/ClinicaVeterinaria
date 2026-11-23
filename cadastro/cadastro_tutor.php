
<?php include '../includes/cabecalho.php'; ?>

<style>
    .tutor-container {
        padding-top: 25px;
        padding-bottom: 40px;
    }

    .tutor-banner {
        background: linear-gradient(135deg, #11796f, #159589);
        color: white;
        padding: 35px;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .tutor-banner h1 {
        font-size: 30px;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .tutor-banner p {
        margin-bottom: 0;
        font-size: 16px;
    }

    .tutor-form-card {
        background-color: white;
        border: 1px solid #dedede;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 25px;
    }

    .tutor-form-card h4 {
        color: #11796f;
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 22px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eeeeee;
    }

    .tutor-form-card label {
        font-weight: 500;
        margin-bottom: 7px;
        color: #333333;
    }

    .tutor-form-card .form-control,
    .tutor-form-card .form-select {
        border-radius: 7px;
        padding: 10px 12px;
        border: 1px solid #ced4da;
    }

    .tutor-form-card .form-control:focus,
    .tutor-form-card .form-select:focus {
        border-color: #11796f;
        box-shadow: 0 0 0 0.2rem rgba(17, 121, 111, 0.15);
    }

    .tutor-botao-salvar {
        background-color: #11796f;
        color: white;
        border: none;
        padding: 11px 25px;
        border-radius: 7px;
        transition: 0.3s;
    }

    .tutor-botao-salvar:hover {
        background-color: #0d625a;
        color: white;
    }

    .tutor-botao-voltar {
        background-color: white;
        color: #11796f;
        border: 1px solid #11796f;
        padding: 11px 25px;
        border-radius: 7px;
        text-decoration: none;
        transition: 0.3s;
    }

    .tutor-botao-voltar:hover {
        background-color: #eaf5f3;
        color: #0d625a;
    }

    .tutor-obrigatorio {
        color: #dc3545;
    }

    @media (max-width: 768px) {
        .tutor-banner {
            padding: 25px;
        }

        .tutor-banner h1 {
            font-size: 25px;
        }

        .tutor-form-card {
            padding: 20px;
        }
    }
</style>

<div class="container tutor-container">

    <!-- Banner -->
    <div class="tutor-banner">
        <h1>Cadastro de Tutor</h1>

        <p>
            Preencha os dados abaixo para cadastrar um novo
            responsável pelos animais na clínica.
        </p>
    </div>

    <form method="POST" action="">

        <!-- Dados pessoais -->
        <div class="tutor-form-card">

            <h4>Dados pessoais</h4>

            <div class="row">

                <div class="col-md-8 mb-3">
                    <label for="nome">
                        Nome completo <span class="tutor-obrigatorio">*</span>
                    </label>

                    <input type="text"
                           class="form-control"
                           id="nome"
                           name="nome"
                           placeholder="Digite o nome completo"
                           required>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="cpf">
                        CPF <span class="tutor-obrigatorio">*</span>
                    </label>

                    <input type="text"
                           class="form-control"
                           id="cpf"
                           name="cpf"
                           placeholder="000.000.000-00"
                           required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="telefone">
                        Telefone <span class="tutor-obrigatorio">*</span>
                    </label>

                    <input type="tel"
                           class="form-control"
                           id="telefone"
                           name="telefone"
                           placeholder="(00) 00000-0000"
                           required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="email">E-mail</label>

                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           placeholder="exemplo@email.com">
                </div>

            </div>

        </div>

        <!-- Endereço -->
        <div class="tutor-form-card">

            <h4>Endereço</h4>

            <div class="row">

                <div class="col-md-3 mb-3">
                    <label for="cep">CEP</label>

                    <input type="text"
                           class="form-control"
                           id="cep"
                           name="cep"
                           placeholder="00000-000">
                </div>

                <div class="col-md-7 mb-3">
                    <label for="logradouro">Rua / Avenida</label>

                    <input type="text"
                           class="form-control"
                           id="logradouro"
                           name="logradouro"
                           placeholder="Nome da rua ou avenida">
                </div>

                <div class="col-md-2 mb-3">
                    <label for="numero">Número</label>

                    <input type="text"
                           class="form-control"
                           id="numero"
                           name="numero"
                           placeholder="Nº">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="bairro">Bairro</label>

                    <input type="text"
                           class="form-control"
                           id="bairro"
                           name="bairro"
                           placeholder="Bairro">
                </div>

                <div class="col-md-5 mb-3">
                    <label for="cidade">Cidade</label>

                    <input type="text"
                           class="form-control"
                           id="cidade"
                           name="cidade"
                           placeholder="Cidade">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="estado">Estado</label>

                    <select class="form-select"
                            id="estado"
                            name="estado">

                        <option value="">Selecione</option>
                        <option value="AC">Acre</option>
                        <option value="AL">Alagoas</option>
                        <option value="AP">Amapá</option>
                        <option value="AM">Amazonas</option>
                        <option value="BA">Bahia</option>
                        <option value="CE">Ceará</option>
                        <option value="DF">Distrito Federal</option>
                        <option value="ES">Espírito Santo</option>
                        <option value="GO">Goiás</option>
                        <option value="MA">Maranhão</option>
                        <option value="MT">Mato Grosso</option>
                        <option value="MS">Mato Grosso do Sul</option>
                        <option value="MG">Minas Gerais</option>
                        <option value="PA">Pará</option>
                        <option value="PB">Paraíba</option>
                        <option value="PR">Paraná</option>
                        <option value="PE">Pernambuco</option>
                        <option value="PI">Piauí</option>
                        <option value="RJ">Rio de Janeiro</option>
                        <option value="RN">Rio Grande do Norte</option>
                        <option value="RS">Rio Grande do Sul</option>
                        <option value="RO">Rondônia</option>
                        <option value="RR">Roraima</option>
                        <option value="SC">Santa Catarina</option>
                        <option value="SP">São Paulo</option>
                        <option value="SE">Sergipe</option>
                        <option value="TO">Tocantins</option>

                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="complemento">Complemento</label>

                    <input type="text"
                           class="form-control"
                           id="complemento"
                           name="complemento"
                           placeholder="Apartamento, casa, etc.">
                </div>

            </div>

        </div>

        <!-- Observações -->
        <div class="tutor-form-card">

            <h4>Observações</h4>

            <div class="mb-3">
                <label for="observacoes">Informações adicionais</label>

                <textarea class="form-control"
                          id="observacoes"
                          name="observacoes"
                          rows="3"
                          placeholder="Digite alguma observação, se necessário"></textarea>
            </div>

        </div>

        <!-- Botões -->
        <div class="d-flex justify-content-between flex-wrap gap-2">

            <a href="cadastro.php" class="tutor-botao-voltar">
                ← Voltar
            </a>

            <button type="submit" class="tutor-botao-salvar">
                Cadastrar Tutor
            </button>

        </div>

    </form>

</div>

<?php include '../includes/rodape.php'; ?>
