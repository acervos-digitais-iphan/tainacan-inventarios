<?php
/*
Plugin Name: Tainacan Inventários
Description: Plugin para gerenciar inventários no Tainacan, baseando-se nos requisitos do INRC.
Version: 0.1.0
Author: mateuswetah
Text Domain: tainacan-inventarios
Requires Plugins: tainacan
*/

const TAINACAN_INVENTARIOS_VERSION = '0.1.0';

// Evita acesso direto ao arquivo
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function teste_extrair_valor($serialized) {

    $array = @unserialize($serialized);

    if (is_array($array)) {
        // Garantir que todos os valores sejam convertidos para string
        $strings = array_map(function ($item) {
            if (is_array($item)) {
                return '[array]';
            } elseif (is_object($item)) {
                return '[objeto]';
            } else {
                return (string) $item;
            }
        }, $array);

        $legivel = implode(', ', $strings);
        return $legivel;
    } else {
        return 'Formato inválido ou não serializado';
    }
}

/* ----------------------------- IMPORTS  ----------------------------- */
require_once __DIR__ . '/traits/singleton.php';

require_once __DIR__ . '/inc/inventario-post-type.php';
Tainacan_Inventarios\Inventario_Post_Type::get_instance();

require_once __DIR__ . '/inc/restrict-users-by-team.php';
Tainacan_Inventarios\Restrict_Users_By_Team::get_instance();

require_once __DIR__ . '/inc/control-collections.php';
Tainacan_Inventarios\Control_Collections::get_instance();

require_once __DIR__ . '/inc/expanded-filter-relationship.php';
Tainacan_Inventarios\Expanded_Filter_Relationship::get_instance();
