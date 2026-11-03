<?php
$base = '../../';
include __DIR__ . '/../../includes/cabecalho.php';
include __DIR__ . '/../../includes/conexao.php';
include __DIR__ . '/../../includes/funcoes.php';

$erro = '';

$animais = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        'SELECT Animalid, Nome, Especie, Raca, DataNascimento, Cor
           FROM animais
          ORDER BY Nome'
    ),
    MYSQLI_ASSOC
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animalid  = (int) ($_POST['animalid'] ?? 0);
    $vacinaid  = (int) ($_POST['vacinaid'] ?? 0);
    $dose      = trim($_POST['dose'] ?? '');
    $aplicacao = trim($_POST['aplicacao'] ?? '');
    $retorno   = trim($_POST['retorno'] ?? '');

    if ($animalid <= 0) {
        $erro = 'Selecione o animal.';
    } elseif ($vacinaid <= 0) {
        $erro = 'Selecione a vacina.';
    } elseif (!ctype_digit($dose) || (int) $dose < 1) {
        $erro = 'Informe a quantidade aplicada (maior que zero).';
    } elseif (!data_valida($aplicacao)) {
        $erro = 'Informe uma data de aplicação válida.';
    } elseif ($retorno !== '' && !data_valida($retorno)) {
        $erro = 'Informe uma data de retorno válida.';
    } elseif ($retorno !== '' && $retorno < $aplicacao) {
        $erro = 'A data de retorno não pode ser anterior à aplicação.';
    } else {
        // Confere se o animal existe
        $stmt = mysqli_prepare($conexao, 'SELECT Animalid FROM animais WHERE Animalid = ?');
        mysqli_stmt_bind_param($stmt, 'i', $animalid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $animalExiste = mysqli_stmt_num_rows($stmt) === 1;

        if (!$animalExiste) {
            $erro = 'Selecione um animal válido.';
        } else {
            // Busca o lote escolhido
            $stmt = mysqli_prepare(
                $conexao,
                'SELECT Vacinaid, TipoVacina, QuantidadeLote, DataValidade
                   FROM vacinas
                  WHERE Vacinaid = ?'
            );
            mysqli_stmt_bind_param($stmt, 'i', $vacinaid);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            $vacina = $resultado ? mysqli_fetch_assoc($resultado) : null;

            if (!$vacina) {
                $erro = 'A vacina selecionada não foi encontrada.';
            } elseif ($vacina['DataValidade'] < date('Y-m-d')) {
                $erro = 'Este lote está vencido e não pode ser aplicado.';
            } elseif ((int) $vacina['QuantidadeLote'] < (int) $dose) {
                $erro = 'Quantidade insuficiente no lote selecionado.';
            } else {
                $doseInt = (int) $dose;
                $retornoParam = $retorno === '' ? null : $retorno;
                $tipo = $vacina['TipoVacina'];

                mysqli_begin_transaction($conexao);

                try {
                    // Dá baixa no lote de vacinas
                    $stmt = mysqli_prepare(
                        $conexao,
                        'UPDATE vacinas
                            SET QuantidadeLote = QuantidadeLote - ?
                          WHERE Vacinaid = ? AND QuantidadeLote >= ?'
                    );
                    mysqli_stmt_bind_param($stmt, 'iii', $doseInt, $vacinaid, $doseInt);

                    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                        throw new Exception('Não foi possível dar baixa no lote. Tente novamente.');
                    }

                    // Registra a aplicação
                    $stmt = mysqli_prepare(
                        $conexao,
                        'INSERT INTO vacinas_aplicacadas (DataAplicacao, Retorno, dose, TipoVacina, Animalid)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    mysqli_stmt_bind_param($stmt, 'ssisi', $aplicacao, $retornoParam, $doseInt, $tipo, $animalid);

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception('Erro ao salvar a aplicação: ' . mysqli_error($conexao));
                    }

                    mysqli_commit($conexao);

                    header('Location: index.php?ok=1');
                    exit;
                } catch (Exception $e) {
                    mysqli_rollback($conexao);
                    $erro = $e->getMessage();
                }
            }
        }
    }
}

// Lotes disponíveis para aplicação (com estoque e dentro da validade)
$vacinas = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        "SELECT Vacinaid, TipoVacina, DataValidade, QuantidadeLote
           FROM vacinas
          WHERE QuantidadeLote > 0
            AND DataValidade >= CURDATE()
          ORDER BY DataValidade, TipoVacina"
    ),
    MYSQLI_ASSOC
);

// Mantém o animal escolhido se o formulário voltar com erro
$animalSelecionado = null;
if (isset($_POST['animalid'])) {
    foreach ($animais as $animal) {
        if ((int) $animal['Animalid'] === (int) $_POST['animalid']) {
            $animalSelecionado = $animal;
            break;
        }
    }
}

$aplicacoes = mysqli_fetch_all(
    mysqli_query(
        $conexao,
        'SELECT ap.Aplicacaoid, ap.DataAplicacao, ap.Retorno, ap.dose, ap.TipoVacina, an.Nome AS Animal
           FROM vacinas_aplicacadas ap
           LEFT JOIN animais an ON an.Animalid = ap.Animalid
          ORDER BY ap.DataAplicacao DESC, ap.Aplicacaoid DESC'
    ),
    MYSQLI_ASSOC
);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h4 mb-1">Aplicação Vacinas</h1>
        <p class="text-secondary mb-0">Registro das doses aplicadas</p>
    </div>
    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Voltar
    </a>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= h($erro) ?></div>
<?php endif; ?>

<?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Aplicação registrada e estoque atualizado!</div>
<?php endif; ?>

<?php if (count($animais) === 0): ?>
    <div class="alert alert-warning">
        Nenhum animal cadastrado ainda.
        <a href="<?= $base ?>cadastro/animais/index.php" class="alert-link">Cadastre um animal</a>
        para poder registrar a aplicação.
    </div>
<?php endif; ?>

<?php if (count($vacinas) === 0): ?>
    <div class="alert alert-warning">
        Nenhum lote de vacina disponível em estoque.
        <a href="../lote/index.php" class="alert-link">Cadastre um lote</a>.
    </div>
<?php endif; ?>

<div class="row g-4">

    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">
                    <i class="bi bi-plus-circle me-1"></i> Nova aplicação
                </h2>

                <form method="post" action="index.php" id="formAplicacao" class="row g-3" novalidate>
                    <input type="hidden" name="animalid" id="animalid"
                           value="<?= $animalSelecionado ? (int) $animalSelecionado['Animalid'] : '' ?>">
                    <input type="hidden" name="vacinaid" id="vacinaid" value="">
                    <input type="hidden" name="dose" id="dose" value="">

                    <div class="col-12">
                        <label class="form-label">Animal</label>
                        <button type="button"
                                class="btn btn-outline-secondary w-100 text-start d-flex align-items-center gap-2"
                                data-bs-toggle="modal" data-bs-target="#modalAnimal">
                            <span id="animalIcone" class="icone-animal <?= $animalSelecionado ? '' : 'd-none' ?>"
                                  aria-hidden="true"><?= $animalSelecionado ? icone_especie($animalSelecionado['Especie']) : '' ?></span>
                            <span id="animalTexto" class="<?= $animalSelecionado ? '' : 'text-secondary' ?>">
                                <?= $animalSelecionado ? h($animalSelecionado['Nome']) : 'Selecione o animal' ?>
                            </span>
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </button>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Tipo da vacina</label>
                        <button type="button"
                                class="btn btn-outline-secondary w-100 text-start d-flex align-items-center gap-2"
                                data-bs-toggle="modal" data-bs-target="#modalVacina">
                            <i class="bi bi-syringe"></i>
                            <span>Escolher vacina e quantidade</span>
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </button>
                        <div class="form-text">
                            A quantidade é escolhida no modal e dá baixa no lote de vacinas.
                        </div>
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="aplicacao" class="form-label">Data da aplicação</label>
                        <input type="date" class="form-control" id="aplicacao" name="aplicacao"
                               required value="<?= h($_POST['aplicacao'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="retorno" class="form-label">Retorno (opcional)</label>
                        <input type="date" class="form-control" id="retorno" name="retorno"
                               value="<?= h($_POST['retorno'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Selecione o animal, confira a data e clique em
                            <strong>Escolher vacina e quantidade</strong> para aplicar a dose.
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">
                    <i class="bi bi-list-ul me-1"></i> Aplicações registradas
                </h2>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Animal</th>
                                <th>Vacina</th>
                                <th class="text-end">Dose</th>
                                <th>Aplicação</th>
                                <th>Retorno</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($aplicacoes) === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-3">
                                        Nenhuma aplicação registrada.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($aplicacoes as $item): ?>
                                    <tr>
                                        <td><?= h($item['Animal'] ?? '—') ?></td>
                                        <td><?= h($item['TipoVacina']) ?></td>
                                        <td class="text-end"><?= (int) $item['dose'] ?></td>
                                        <td><?= data_br($item['DataAplicacao']) ?></td>
                                        <td><?= data_br($item['Retorno']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ============ MODAL: ESCOLHER ANIMAL ============ -->
<div class="modal fade" id="modalAnimal" tabindex="-1" aria-labelledby="modalAnimalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalAnimalLabel">
                    <i class="bi bi-heart-pulse me-1"></i> Escolher animal
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="buscaAnimal"
                           placeholder="Pesquisar por nome, espécie ou raça..." autocomplete="off"
                           aria-label="Pesquisar animal">
                </div>

                <?php if (count($animais) === 0): ?>
                    <p class="text-secondary mb-0">
                        Nenhum animal cadastrado.
                        <a href="<?= $base ?>cadastro/animais/index.php">Cadastre um animal</a>.
                    </p>
                <?php else: ?>
                    <div class="list-group" id="listaAnimais">
                        <?php foreach ($animais as $animal): ?>
                            <?php
                            $buscaAnimal = mb_strtolower(
                                $animal['Nome'] . ' ' . $animal['Especie'] . ' ' . $animal['Raca'] . ' ' . $animal['Cor'],
                                'UTF-8'
                            );
                            ?>
                            <button type="button"
                                    class="list-group-item list-group-item-action d-flex align-items-center gap-3 animal-opcao"
                                    data-animalid="<?= (int) $animal['Animalid'] ?>"
                                    data-nome="<?= h($animal['Nome']) ?>"
                                    data-busca="<?= h($buscaAnimal) ?>">
                                <span class="icone-animal" aria-hidden="true"><?= icone_especie($animal['Especie']) ?></span>
                                <span class="visually-hidden">Espécie: <?= h($animal['Especie']) ?></span>
                                <span class="flex-grow-1 text-start">
                                    <strong class="d-block"><?= h($animal['Nome']) ?></strong>
                                    <span class="small text-secondary">
                                        <?= h($animal['Raca']) ?> · <?= idade($animal['DataNascimento']) ?>
                                    </span>
                                </span>
                                <i class="bi bi-chevron-right text-secondary"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <p class="small text-secondary mt-3 mb-0 d-none" id="semResultadoAnimal">
                        Nenhum animal encontrado.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============ MODAL: ESCOLHER VACINA ============ -->
<div class="modal fade" id="modalVacina" tabindex="-1" aria-labelledby="modalVacinaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalVacinaLabel">
                    <i class="bi bi-syringe me-1"></i> Vacinas disponíveis
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <div id="erroVacina" class="alert alert-danger d-none" role="alert"></div>

                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="buscaVacina"
                           placeholder="Pesquisar vacina..." autocomplete="off"
                           aria-label="Pesquisar vacina">
                </div>

                <?php if (count($vacinas) === 0): ?>
                    <p class="text-secondary mb-0">
                        Nenhuma vacina disponível em estoque.
                        <a href="../lote/index.php">Cadastre um lote</a>.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Vacina</th>
                                    <th>Validade</th>
                                    <th class="text-end">Disponível</th>
                                    <th class="text-center">Quantidade</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="listaVacinas">
                                <?php foreach ($vacinas as $vacina): ?>
                                    <?php
                                    $buscaVacina = mb_strtolower(
                                        $vacina['TipoVacina'] . ' ' . data_br($vacina['DataValidade']),
                                        'UTF-8'
                                    );
                                    ?>
                                    <tr class="vacina-opcao" data-busca="<?= h($buscaVacina) ?>">
                                        <td>
                                            <i class="bi bi-syringe me-1 text-secondary"></i>
                                            <strong><?= h($vacina['TipoVacina']) ?></strong>
                                        </td>
                                        <td><?= data_br($vacina['DataValidade']) ?></td>
                                        <td class="text-end"><?= (int) $vacina['QuantidadeLote'] ?></td>
                                        <td>
                                            <div class="input-group input-group-sm controle-quantidade mx-auto">
                                                <button type="button" class="btn btn-outline-secondary btn-menos"
                                                        aria-label="Diminuir quantidade">
                                                    <i class="bi bi-dash-lg"></i>
                                                </button>
                                                <input type="number" class="form-control text-center qtd-vacina"
                                                       value="0" min="0" max="<?= (int) $vacina['QuantidadeLote'] ?>"
                                                       step="1" inputmode="numeric" aria-label="Quantidade a aplicar">
                                                <button type="button" class="btn btn-outline-secondary btn-mais"
                                                        aria-label="Aumentar quantidade">
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-sistema btn-aplicar"
                                                    data-vacinaid="<?= (int) $vacina['Vacinaid'] ?>"
                                                    data-max="<?= (int) $vacina['QuantidadeLote'] ?>">
                                                <i class="bi bi-check-lg"></i> Aplicar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="small text-secondary mt-3 mb-0 d-none" id="semResultadoVacina">
                        Nenhuma vacina encontrada.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function normalizar(texto) {
        return String(texto).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function filtrar(itens, termo, mensagemVazio) {
        const busca = normalizar(termo.trim());
        let visiveis = 0;

        itens.forEach(function (item) {
            const texto = normalizar(item.dataset.busca || item.textContent);
            const mostrar = busca === '' || texto.includes(busca);
            item.classList.toggle('d-none', !mostrar);
            if (mostrar) {
                visiveis++;
            }
        });

        if (mensagemVazio) {
            mensagemVazio.classList.toggle('d-none', visiveis > 0);
        }
    }

    // ---------- Modal de animais ----------
    const modalAnimal = document.getElementById('modalAnimal');
    const buscaAnimal = document.getElementById('buscaAnimal');
    const animais = Array.from(document.querySelectorAll('.animal-opcao'));
    const semResultadoAnimal = document.getElementById('semResultadoAnimal');

    if (modalAnimal) {
        modalAnimal.addEventListener('show.bs.modal', function () {
            if (buscaAnimal) {
                buscaAnimal.value = '';
                filtrar(animais, '', semResultadoAnimal);
            }
        });
    }

    if (buscaAnimal) {
        buscaAnimal.addEventListener('input', function () {
            filtrar(animais, buscaAnimal.value, semResultadoAnimal);
        });
    }

    animais.forEach(function (opcao) {
        opcao.addEventListener('click', function () {
            const iconeOpcao = opcao.querySelector('.icone-animal');
            const iconeAlvo = document.getElementById('animalIcone');
            const textoAlvo = document.getElementById('animalTexto');

            document.getElementById('animalid').value = opcao.dataset.animalid;

            iconeAlvo.textContent = iconeOpcao ? iconeOpcao.textContent : '';
            iconeAlvo.classList.remove('d-none');

            textoAlvo.textContent = opcao.dataset.nome;
            textoAlvo.classList.remove('text-secondary');

            const modal = bootstrap.Modal.getInstance(modalAnimal);
            if (modal) {
                modal.hide();
            }
        });
    });

    // ---------- Modal de vacinas ----------
    const modalVacina = document.getElementById('modalVacina');
    const buscaVacina = document.getElementById('buscaVacina');
    const vacinas = Array.from(document.querySelectorAll('.vacina-opcao'));
    const semResultadoVacina = document.getElementById('semResultadoVacina');
    const erroVacina = document.getElementById('erroVacina');
    const form = document.getElementById('formAplicacao');

    function mostrarErroVacina(mensagem) {
        erroVacina.textContent = mensagem;
        erroVacina.classList.remove('d-none');
    }

    function limparErroVacina() {
        erroVacina.textContent = '';
        erroVacina.classList.add('d-none');
    }

    if (modalVacina) {
        modalVacina.addEventListener('show.bs.modal', function () {
            limparErroVacina();

            if (buscaVacina) {
                buscaVacina.value = '';
                filtrar(vacinas, '', semResultadoVacina);
            }

            document.querySelectorAll('.qtd-vacina').forEach(function (input) {
                input.value = 0;
            });
        });
    }

    if (buscaVacina) {
        buscaVacina.addEventListener('input', function () {
            filtrar(vacinas, buscaVacina.value, semResultadoVacina);
        });
    }

    document.querySelectorAll('.qtd-vacina').forEach(function (input) {
        input.addEventListener('focus', function () {
            input.select();
        });

        input.addEventListener('change', function () {
            let valor = parseInt(input.value, 10);
            const max = parseInt(input.max, 10) || 0;

            if (isNaN(valor) || valor < 0) {
                valor = 0;
            }
            if (valor > max) {
                valor = max;
            }

            input.value = valor;
        });
    });

    document.querySelectorAll('.btn-menos, .btn-mais').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const grupo = botao.closest('.controle-quantidade');
            const input = grupo.querySelector('.qtd-vacina');
            const max = parseInt(input.max, 10) || 0;
            let valor = parseInt(input.value, 10) || 0;

            valor += botao.classList.contains('btn-mais') ? 1 : -1;

            if (valor < 0) {
                valor = 0;
            }
            if (valor > max) {
                valor = max;
            }

            input.value = valor;
        });
    });

    document.querySelectorAll('.btn-aplicar').forEach(function (botao) {
        botao.addEventListener('click', function () {
            limparErroVacina();

            const animalid = document.getElementById('animalid').value;
            const data = document.getElementById('aplicacao').value;
            const retorno = document.getElementById('retorno').value;
            const input = botao.closest('tr').querySelector('.qtd-vacina');
            const dose = parseInt(input.value, 10) || 0;
            const max = parseInt(botao.dataset.max, 10) || 0;

            if (animalid === '') {
                mostrarErroVacina('Selecione o animal antes de aplicar a vacina.');
                return;
            }
            if (data === '') {
                mostrarErroVacina('Informe a data da aplicação.');
                return;
            }
            if (retorno !== '' && retorno < data) {
                mostrarErroVacina('A data de retorno não pode ser anterior à aplicação.');
                return;
            }
            if (dose < 1) {
                mostrarErroVacina('Informe uma quantidade maior que zero.');
                return;
            }
            if (dose > max) {
                mostrarErroVacina('A quantidade informada é maior que o disponível no lote.');
                return;
            }

            document.getElementById('vacinaid').value = botao.dataset.vacinaid;
            document.getElementById('dose').value = dose;

            botao.disabled = true;
            botao.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aplicando...';

            form.submit();
        });
    });
})();
</script>

<?php include __DIR__ . '/../../includes/rodape.php'; ?>
