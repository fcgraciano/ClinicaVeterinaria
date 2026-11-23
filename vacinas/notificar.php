<?php
/**
 * ============================================================
 *  MÓDULO: VACINAS
 *  Arquivo: vacinas/notificar.php
 *
 *  Gera as notificações de vacinas a vencer e envia os e-mails
 *  pendentes.
 *
 *  Como rodar:
 *    - Pelo navegador:
 *        http://localhost/ClinicaVeterinaria/vacinas/notificar.php
 *    - Pela linha de comando (XAMPP):
 *        C:\xampp\php\php.exe C:\caminho\ClinicaVeterinaria\vacinas\notificar.php
 *    - Agendador de Tarefas do Windows / cron: use o comando acima
 *      uma vez por dia.
 *
 *  OBS.: o Event do MySQL (04_vacinas.sql) já cria as notificações
 *  diariamente. Este script pode ser usado apenas para enviá-las,
 *  ou como alternativa ao Event. A chamada da procedure é
 *  idempotente, então rodar os dois não gera duplicidade.
 * ============================================================
 */

require_once __DIR__ . '/../includes/conexao.php';

// Quantos dias de antecedência para avisar o tutor.
const DIAS_ANTECEDENCIA = 30;

// Em produção, troque para true (precisa de um servidor de e-mail
// configurado no php.ini). Com false, o script apenas registra no
// log e marca como enviada, para testar o fluxo.
const ENVIAR_EMAIL = false;

const ARQUIVO_LOG = __DIR__ . '/log_notificacoes.txt';

$ehWeb = PHP_SAPI !== 'cli';

function registrarLog(string $linha): void
{
    file_put_contents(
        ARQUIVO_LOG,
        '[' . date('Y-m-d H:i:s') . '] ' . $linha . PHP_EOL,
        FILE_APPEND
    );
}

// ------------------------------------------------------------
// 1) Gera as notificações pendentes (não duplica graças à
//    chave única da tabela notificacoes).
// ------------------------------------------------------------
$conexao->query('CALL sp_gerar_notificacoes_vacinas(' . (int) DIAS_ANTECEDENCIA . ')');

// Descarta possíveis result sets extras da procedure.
while ($conexao->more_results() && $conexao->next_result()) {
    // nada
}

// ------------------------------------------------------------
// 2) Busca as notificações pendentes
// ------------------------------------------------------------
$sql = "
    SELECT
        n.id,
        n.canal,
        n.mensagem,
        t.nome  AS tutor,
        t.email AS email,
        a.Nome  AS animal,
        v.TipoVacina AS vacina,
        va.data_proxima_dose
    FROM notificacoes n
    JOIN tutores           t  ON t.id      = n.tutor_id
    JOIN animais           a  ON a.Animalid = n.animal_id
    JOIN vacinas_aplicadas va ON va.id     = n.vacina_aplicada_id
    JOIN vacinas           v  ON v.Vacinaid = va.vacina_id
    WHERE n.status = 'pendente'
    ORDER BY va.data_proxima_dose ASC, n.id ASC
";

$resultado = $conexao->query($sql);

if (!$resultado) {
    registrarLog('ERRO ao buscar notificações: ' . $conexao->error);

    if ($ehWeb) {
        include __DIR__ . '/../includes/cabecalho.php';
        echo '<h1 class="h3 mb-3">Notificações de vacinas</h1>';
        echo '<div class="alert alert-danger">Erro ao buscar notificações: '
            . htmlspecialchars($conexao->error) . '</div>';
        echo '<a class="btn btn-outline-secondary btn-sm" href="index.php">Voltar</a>';
        include __DIR__ . '/../includes/rodape.php';
    } else {
        echo 'Erro ao buscar notificações: ' . $conexao->error . PHP_EOL;
    }

    exit(1);
}

$atualizar = $conexao->prepare(
    "UPDATE notificacoes
        SET status = ?, enviada_em = ?, erro = ?
      WHERE id = ?"
);

$total    = 0;
$enviadas = 0;
$erros    = 0;

while ($n = $resultado->fetch_assoc()) {
    $total++;

    $status = 'erro';
    $erro   = null;

    if ($n['canal'] !== 'email') {
        $erro = 'Canal não suportado por este script: ' . $n['canal'];
    } elseif (!filter_var($n['email'], FILTER_VALIDATE_EMAIL)) {
        $erro = 'Tutor sem e-mail válido.';
    } elseif (!ENVIAR_EMAIL) {
        // Modo de teste: não envia de verdade, mas conclui o fluxo.
        $status = 'enviada';
        registrarLog("[SIMULADO] Para {$n['email']} | {$n['mensagem']}");
    } else {
        $assunto = 'Lembrete de vacina: ' . $n['animal'];

        $cabecalhos = implode("\r\n", [
            'From: Patas & Cia <nao-responda@patas.local>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]);

        $assuntoCodificado = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

        if (@mail($n['email'], $assuntoCodificado, $n['mensagem'], $cabecalhos)) {
            $status = 'enviada';
            registrarLog("E-mail enviado para {$n['email']} (notificação #{$n['id']}).");
        } else {
            // Mantém como pendente para tentar novamente na próxima execução.
            $status = 'pendente';
            $erro   = 'Falha ao enviar pelo mail(). Verifique a configuração do php.ini.';
        }
    }

    $enviadaEm = ($status === 'enviada') ? date('Y-m-d H:i:s') : null;

    $atualizar->bind_param('sssi', $status, $enviadaEm, $erro, $n['id']);
    $atualizar->execute();

    if ($status === 'enviada') {
        $enviadas++;
    } else {
        $erros++;
        registrarLog("ERRO na notificação #{$n['id']}: {$erro}");
    }
}

$resumo = "Notificações processadas: {$total} | enviadas: {$enviadas} | erros: {$erros}";
registrarLog($resumo);

if ($ehWeb) {
    include __DIR__ . '/../includes/cabecalho.php';

    echo '<h1 class="h3 mb-3">Notificações de vacinas</h1>';
    echo '<div class="alert alert-success">' . htmlspecialchars($resumo) . '</div>';

    if (!ENVIAR_EMAIL) {
        echo '<div class="alert alert-warning"><strong>Modo de teste:</strong> '
            . 'os e-mails não foram enviados de verdade. Para enviar, troque '
            . '<code>ENVIAR_EMAIL</code> para <code>true</code> em '
            . '<code>vacinas/notificar.php</code>.</div>';
    }

    echo '<p class="small text-secondary">Log: '
        . htmlspecialchars(ARQUIVO_LOG) . '</p>';
    echo '<a class="btn btn-outline-secondary btn-sm" href="index.php">'
        . 'Voltar para Vacinas</a>';

    include __DIR__ . '/../includes/rodape.php';
} else {
    echo $resumo . PHP_EOL;
}
