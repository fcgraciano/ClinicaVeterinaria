<?php
// ===============================================
//  FUNÇÕES AUXILIARES
//  Use nas páginas assim:
//      include '../includes/funcoes.php';
// ===============================================

// Escapa um texto para exibir na página com segurança
function h($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Confere se a data está no formato AAAA-MM-DD e existe no calendário
function data_valida($data)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $data);
    return $d && $d->format('Y-m-d') === $data;
}

// Mostra a data no formato brasileiro (DD/MM/AAAA)
function data_br($data)
{
    if (empty($data)) {
        return '—';
    }
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d ? $d->format('d/m/Y') : $data;
}

// Converte um número digitado com vírgula (ex.: 12,5) para float
function numero_br($valor)
{
    $valor = str_replace(',', '.', trim((string) $valor));
    return is_numeric($valor) ? (float) $valor : null;
}

// Calcula a idade a partir da data de nascimento (AAAA-MM-DD)
function idade($data)
{
    if (!data_valida($data)) {
        return '—';
    }

    $nascimento = new DateTime($data);
    $hoje = new DateTime('today');

    if ($nascimento > $hoje) {
        return '—';
    }

    $diff = $nascimento->diff($hoje);

    if ($diff->y > 0) {
        return $diff->y . ($diff->y === 1 ? ' ano' : ' anos');
    }
    if ($diff->m > 0) {
        return $diff->m . ($diff->m === 1 ? ' mês' : ' meses');
    }
    return $diff->d . ($diff->d === 1 ? ' dia' : ' dias');
}

// Retorna um ícone (emoji) que representa a espécie do animal
function icone_especie($especie)
{
    $especie = mb_strtolower((string) $especie, 'UTF-8');

    if (str_contains($especie, 'cachorro') || str_contains($especie, 'cão') || str_contains($especie, 'cao') || str_contains($especie, 'canin')) {
        return '🐕';
    }
    if (str_contains($especie, 'gato') || str_contains($especie, 'felino')) {
        return '🐈';
    }
    if (str_contains($especie, 'ave') || str_contains($especie, 'pássaro') || str_contains($especie, 'passaro')) {
        return '🐦';
    }
    if (str_contains($especie, 'coelho') || str_contains($especie, 'lepor')) {
        return '🐇';
    }
    if (str_contains($especie, 'roedor') || str_contains($especie, 'hamster') || str_contains($especie, 'rato') || str_contains($especie, 'camundongo')) {
        return '🐹';
    }
    if (str_contains($especie, 'réptil') || str_contains($especie, 'reptil') || str_contains($especie, 'tartaruga') || str_contains($especie, 'lagarto') || str_contains($especie, 'cobra')) {
        return '🦎';
    }

    return '🐾';
}
