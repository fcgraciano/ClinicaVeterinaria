<?php
require_once __DIR__ . '/../includes/conexao.php';

$mensagem     = '';
$tipoMensagem = 'success';

// Botão "Gerar notificações agora" (a rotina diária faz isso sozinha).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['acao'] ?? '') === 'gerar') {
    if ($conexao->query('CALL sp_gerar_notificacoes_vacinas(30)')) {
        $mensagem = 'Notificações geradas. Veja a quantidade de pendentes abaixo.';
    } else {
        $tipoMensagem = 'danger';
        $mensagem     = 'Erro ao gerar notificações: ' . $conexao->error;
    }

    while ($conexao->more_results() && $conexao->next_result()) {
        // descarta resultados extras da procedure
    }
}

$resumo = $conexao->query("
    SELECT
        COALESCE(SUM(dias_para_vencer < 0), 0)              AS vencidas,
        COALESCE(SUM(dias_para_vencer BETWEEN 0 AND 7), 0)  AS proximos7,
        COALESCE(SUM(dias_para_vencer BETWEEN 8 AND 30), 0) AS ate30
    FROM vw_vacinas_a_vencer
    WHERE dias_para_vencer <= 30
");

$lista = $conexao->query("
    SELECT *
    FROM vw_vacinas_a_vencer
    WHERE dias_para_vencer <= 30
    ORDER BY dias_para_vencer ASC, animal ASC
");

$pendentes = $conexao->query("
    SELECT COUNT(*) AS total
    FROM notificacoes
    WHERE status = 'pendente'
");

$erroBanco = (!$resumo || !$lista || !$pendentes) ? $conexao->error : '';

$dados = $resumo
    ? $resumo->fetch_assoc()
    : ['vencidas' => 0, 'proximos7' => 0, 'ate30' => 0];

$totalPendentes = $pendentes
    ? (int) $pendentes->fetch_assoc()['total']
    : 0;

include '../includes/cabecalho.php';
?>

<h1 class="h3 mb-1">Vacinas a vencer</h1>
<p class="text-secondary mb-4">
    Animais com vacina vencida ou com próxima dose nos próximos 30 dias.
</p>

<?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipoMensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
<?php endif; ?>

<?php if ($erroBanco): ?>
    <div class="alert alert-danger">
        <strong>Banco de dados não configurado.</strong>
        Rode o arquivo <code>banco/04_vacinas.sql</code> no phpMyAdmin.
        <br><small><?= htmlspecialchars($erroBanco) ?></small>
    </div>
<?php else: ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 border-danger">
            <div class="card-body">
                <div class="text-secondary small">Vencidas</div>
                <div class="fs-3 fw-bold text-danger"><?= (int) $dados['vencidas'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <div class="text-secondary small">Vencem em até 7 dias</div>
                <div class="fs-3 fw-bold text-warning"><?= (int) $dados['proximos7'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 border-info">
            <div class="card-body">
                <div class="text-secondary small">Vencem em 8 a 30 dias</div>
                <div class="fs-3 fw-bold text-info"><?= (int) $dados['ate30'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-secondary small">Notificações pendentes</div>
                <div class="fs-3 fw-bold"><?= $totalPendentes ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <form method="post" class="d-inline">
        <input type="hidden" name="acao" value="gerar">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-bell"></i> Gerar notificações agora
        </button>
    </form>

    <a href="notificar.php" class="btn btn-primary">
        <i class="bi bi-envelope"></i> Processar envios
        <?php if ($totalPendentes > 0): ?>
            <span class="badge text-bg-light ms-1"><?= $totalPendentes ?></span>
        <?php endif; ?>
    </a>
</div>

<p class="small text-secondary">
    A rotina diária do banco cria os lembretes automaticamente. O botão acima força a
    geração agora; o envio dos e-mails é feito pelo botão <strong>Processar envios</strong>.
</p>

<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>Animal</th>
                <th>Vacina</th>
                <th>Próxima dose</th>
                <th>Prazo</th>
                <th>Tutor</th>
                <th>Contato</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($lista->num_rows === 0): ?>
            <tr>
                <td colspan="7" class="text-center text-secondary py-4">
                    Nenhuma vacina vence nos próximos 30 dias.
                </td>
            </tr>
        <?php else: ?>
            <?php while ($v = $lista->fetch_assoc()): ?>
                <?php
                $dias = (int) $v['dias_para_vencer'];

                if ($dias < 0) {
                    $prazo = 'Venceu há ' . abs($dias) . ' dia(s)';
                } elseif ($dias === 0) {
                    $prazo = 'Vence hoje';
                } else {
                    $prazo = 'Faltam ' . $dias . ' dia(s)';
                }

                $cores = [
                    'vencida'        => 'danger',
                    'vence_em_breve' => 'warning',
                    'a_vencer'       => 'info',
                    'em_dia'         => 'success',
                ];
                $rotulos = [
                    'vencida'        => 'Vencida',
                    'vence_em_breve' => 'Vence em breve',
                    'a_vencer'       => 'A vencer',
                    'em_dia'         => 'Em dia',
                ];
                $cor    = $cores[$v['situacao']] ?? 'secondary';
                $rotulo = $rotulos[$v['situacao']] ?? $v['situacao'];
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($v['animal']) ?>
                        <br><small class="text-secondary"><?= htmlspecialchars($v['especie']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($v['vacina']) ?></td>
                    <td><?= date('d/m/Y', strtotime($v['data_proxima_dose'])) ?></td>
                    <td><?= htmlspecialchars($prazo) ?></td>
                    <td><?= htmlspecialchars($v['tutor']) ?></td>
                    <td>
                        <?php if (!empty($v['tutor_email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($v['tutor_email']) ?>">
                                <?= htmlspecialchars($v['tutor_email']) ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($v['tutor_telefone'])): ?>
                            <br><small class="text-secondary"><?= htmlspecialchars($v['tutor_telefone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-<?= $cor ?>"><?= $rotulo ?></span></td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php include '../includes/rodape.php'; ?>
