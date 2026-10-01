<?php

/*
 * Plugin Name:       Metabase Embed
 * Plugin URI:        https://github.com/francoisjun/metabase-embed-wp/
 * Description:       Shortcode para incorporar dashboards do Metabase.
 * Version:           1.2.1
 * Requires PHP:	  7.1
 * Author:            François Júnior
 * Author URI:        https://github.com/francoisjun/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       metabase-embed
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;

function metabase_embed_activate() {
	metabase_setup_post_type(); 
	flush_rewrite_rules(); 
}
register_activation_hook( __FILE__, 'metabase_embed_activate');


function metabase_embed_deactivate() {
	unregister_post_type('panels');
	remove_shortcode('metabase-embed');
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'metabase_embed_deactivate');


function metabase_embed_menu() {
	add_plugins_page(
		__('Configurações do Metabase Embed', 'metabase-embed'),
		__('Metabase Embed', 'metabase-embed'),
		'manage_options',
		'metabase-embed-plugin',
		'metabase_embed_menu_html'
	);
    add_action('admin_init', 'metabase_embed_settings_init');
}
add_action('admin_menu', 'metabase_embed_menu');


function metabase_embed_menu_html() {
	if (!current_user_can('manage_options')) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php settings_errors(); ?>
		<form action="options.php" method="post">
			<?php
			settings_fields('metabase-embed-settings-group');
            do_settings_sections('metabase-embed-plugin');
            submit_button();
			?>
		</form>
	</div>
	<?php
}


function metabase_embed_settings_init() {
	register_setting('metabase-embed-settings-group', 'metabase_site_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	));
	register_setting('metabase-embed-settings-group', 'metabase_secret_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'metabase_embed_sanitize_secret_key',
		'default'           => '',
	));
	register_setting('metabase-embed-settings-group', 'metabase_token_duration', array(
		'type'              => 'integer',
		'sanitize_callback' => 'metabase_embed_sanitize_token_duration',
		'default'           => 1,
	));
    	
	add_settings_section(
        'metabase-embed-settings-section',
        __('Constantes de acesso', 'metabase-embed'),
        'metabase_embed_settings_section_html',
        'metabase-embed-plugin'
	);

    add_settings_section(
        'metabase-embed-settings-help',
        __('Forma de uso', 'metabase-embed'),
        'metabase_embed_settings_help_html',
        'metabase-embed-plugin'
	);

	add_settings_field(
		'metabase-site-url',
		__('URL do Metabase', 'metabase-embed'), 
        'metabase_site_url_html',
		'metabase-embed-plugin',
		'metabase-embed-settings-section'
	);

    add_settings_field(
		'metabase-secret-key',
		__('Chave Secreta', 'metabase-embed'), 
        'metabase_secret_key_html',
		'metabase-embed-plugin',
		'metabase-embed-settings-section'
	);

	add_settings_field(
		'metabase-token-duration',
		__('Tempo de Validade do Token', 'metabase-embed'), 
        'metabase_token_duration_html',
		'metabase-embed-plugin',
		'metabase-embed-settings-section'
	);
}

function metabase_embed_sanitize_secret_key($value) {
	$value = sanitize_text_field($value);

	// Campo enviado em branco: mantém a chave já armazenada.
	if ($value === '') {
		return (string) get_option('metabase_secret_key', '');
	}

	return $value;
}

function metabase_embed_sanitize_token_duration($value) {
	$value = absint($value);

	if ($value < 1) {
		$value = 1;
	} elseif ($value > 24) {
		$value = 24;
	}

	return $value;
}

function metabase_embed_settings_section_html() {
	echo 'Informe as constantes de acesso ao servidor do Metabase';
}

function metabase_embed_settings_help_html() {
	?>
	<p>Para utilizar um dashboad do Metabase, inclua o shortcode <strong>[metabase-embed id=#]</strong>, onde o <strong>#</strong> corresponde ao número do dashboard.</p>
	<p>Exemplo: <pre>[metabase-embed id=2]</pre></p>
	<h4>Parâmetros disponíveis</h4>
	<p>id (default: 1) -> número do dashboad</p>
	<p>border (default: true) -> exibe ou não uma borda ao redor do dashboard</p>
	<p>title (default: true) -> exibe ou não o título do dashboard</p>
	<p>theme (default: white) -> tema do dashboard. Valores possíveis: <strong>night</strong>, <strong>transparent</strong></p>
	<p>filter (default: null) -> filtros a serem passados pela URL no padrão <strong>chave=valor</strong>. Separe os filtros com o caracter <strong>&</strong></p>
	<p>width (default: 100%) -> largura em pixels do dashboard</p>
	<p>height (default: 600) -> altura em pixels do dashboard</p>
	<p>name (default: '') -> nome que será inserido no atributo <strong>id</strong> do iFrame</p>
	<p>style (default: '') -> classe css que será inserida no atributo <strong>class</strong> do iFrame</p>
	<p>lazy (default: false) -> troca o atributo <strong>src</strong> por <strong>data-src</strong> para implementar o lazy loading via código</p>
	<h4>Exemplo completo</h4>
	<pre>[metabase-embed id=2 width=800 height=400 border=false title=true theme=night filter="city=Florence&state=CD" name="meuIframe"]</pre>
	<?php
}

function metabase_site_url_html() {
	$site_url = esc_attr(get_option('metabase_site_url'));
	echo '<input name="metabase_site_url" type="text" id="metabase_site_url" class="regular-text" placeholder="http://localhost:3000" value="'.$site_url.'">';
}

function metabase_secret_key_html() {
	$has_key     = get_option('metabase_secret_key') !== '';
	$placeholder = $has_key
		? esc_attr__('•••••••• (chave já configurada — preencha para substituir)', 'metabase-embed')
		: '';
	echo '<input name="metabase_secret_key" type="password" autocomplete="off" id="metabase_secret_key" class="regular-text" value="" placeholder="'.$placeholder.'">';
}

function metabase_token_duration_html() {
	$time = esc_attr(get_option('metabase_token_duration'));
	echo '<input type="number" id="metabase_token_duration" name="metabase_token_duration" class="small-text" min="1" max="24" value="'.$time.'"> hora(s)';
}

function metabase_embed_shortcode( $atts ) {
    $atts = array_change_key_case((array) $atts, CASE_LOWER);
	$atts = shortcode_atts(
		array('id' => 1, 
			  'width'  => "100%", 
			  'height' => 600,
			  'border' => 'true',
			  'title'  => 'true',
			  'theme'  => null,
			  'filter' => null,
			  'name'   => null,
			  'style'  => null,
			  'lazy'   => false
		),
		$atts,
		'metabase-embed'
	);
	
    $site_url       = esc_url_raw(get_option('metabase_site_url'));
    $secret_key     = (string) get_option('metabase_secret_key');
    $token_duration = absint(get_option('metabase_token_duration'));

	if ($site_url === '' || $secret_key === '') {
		return '';
	}

	if ($token_duration < 1) {
		$token_duration = 1;
	}

	// Filtro individual do usuário (cadastrado pelo administrador no perfil),
	// enviado como parâmetro travado no JWT para que não possa ser alterado.
	$params      = new stdClass();
	$user_filter = (string) get_user_meta(get_current_user_id(), 'user_filter', true);

	if (trim($user_filter) !== '') {
		$decoded = metabase_parse_user_filter($user_filter);

		// Filtro inválido: não exibe o painel, para não liberar os dados sem restrição.
		if ($decoded === null) {
			return '<p>Não foi possível carregar o painel. Contate o administrador.</p>';
		}

		if (!empty($decoded)) {
			$params = (object) $decoded;
		}
	}

	$seconds_per_hour = 3600; //1h em segundos

    $payload    = [
        'resource' => ['dashboard' => intval($atts['id'])],
        'params'   => $params,
        'exp'      => time() + ($token_duration * $seconds_per_hour)
    ];

    $token      = JWT::encode($payload, $secret_key, 'HS256');
    $iframeUrl  = esc_url( rtrim($site_url, '/') . "/embed/dashboard/" . $token . metabase_embed_get_view_params($atts) );

	$iframeId    = ($atts['name'] != null) ? 'id="'. esc_attr($atts['name']) . '"' : '';
	$iframeStyle = ($atts['style'] != null) ? 'class="'. esc_attr($atts['style']) . '"' : '';
	$iframeSrc   = filter_var($atts['lazy'], FILTER_VALIDATE_BOOLEAN) ? 'data-src': 'src';

	return '<iframe '.$iframeId.' '.$iframeStyle.' '.$iframeSrc.'="'.$iframeUrl.'" frameborder="0" width="'.esc_attr($atts['width']).'" height="'.esc_attr($atts['height']).'"></iframe>';
}
add_shortcode('metabase-embed', 'metabase_embed_shortcode');

function metabase_embed_get_view_params($atts) {
	$theme_values    = array('night', 'transparent');
	$bool_values     = array('true', 'false');
	$selected_params = array();
	
	if(isset($atts['theme']) && in_array($atts['theme'], $theme_values)) {
		 array_push($selected_params, 'theme=' . $atts['theme']);
	}

	if(isset($atts['border']) && in_array($atts['border'], $bool_values)){
		array_push($selected_params, 'bordered=' . $atts['border']);
	}

	if(isset($atts['title']) && in_array($atts['title'], $bool_values)){
		array_push($selected_params, 'titled=' . $atts['title']);
	}

	if(isset($atts['filter'])){
		// Aceita apenas pares chave=valor separados por & (sem aspas, espaços ou < >).
		$filter = (string) $atts['filter'];
		if (preg_match('/^[A-Za-z0-9_\-]+=[^&"\'<>\s]*(&[A-Za-z0-9_\-]+=[^&"\'<>\s]*)*$/', $filter)) {
			array_push($selected_params, $filter);
		}
	}

	return '#' . implode('&', $selected_params);
}

/**
 * 
 */

/**
 * Registrar custom post type 
 */
function metabase_setup_post_type()
{
    // tipo Painéis
    $labels = array(
        'name' => 'Painéis',
        'singular_name' => 'Painel',
        'add_new' => 'Adicionar Novo',
        'add_new_item' => 'Adicionar Novo Painel',
        'edit_item' => 'Editar Painel',
        'new_item' => 'Novo Painel',
        'view_item' => 'Ver Painel',
        'item_published' => 'Painel Publicado',
        'item_updated' => 'Painel Atualizado',
        'not_found' => 'Nenhum Painel encontrado',
        'not_found_in_trash' => 'Nenhum Painel encontrado na lixeira',
        'all_items' => 'Todos os Painéis'
    );

    $args = array(
        'labels' => $labels,
        'description' => 'Painéis do Metabase',
        'public' => true,
        'exclude_from_search' => true,
        'show_in_rest' => true,
        'menu_position' => 21,
        'menu_icon' => 'dashicons-chart-bar',
        'supports' => array('title', 'editor'),
        'taxonomies' => array('panels_group'),
    );
    register_post_type('panels', $args);

    // Taxonomia para Painéis
    $labels = array(
        'name' => 'Grupo',
        'singular_name' => 'Grupo',
        'search_items'  => 'Pesquisar Grupos',
        'all_items' => 'Todos os Grupos',
        'edit_item' => 'Editar Grupo',
        'view_item' => 'Visualizar Grupo',
        'update_item' => 'Atualizar Grupo',
        'add_new_item' => 'Adicionar Novo Grupo',
        'not_found' => 'Nenhum Grupo encontrado',
        'no_terms' => 'Nenhum Grupo',
    );
    $args = array(
        'labels' => $labels,
        'description' => 'Grupos de Painéis',
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_tagcloud' => false,
        'default_term' => array('name' => 'Sem Categoria', 'description' => 'Painéis que não serão exibidos automaticamente', 'slug' => 'sem-categoria'),
    );
    register_taxonomy('panels_group', 'panels', $args);
}
add_action('init', 'metabase_setup_post_type');


/**
 * Registrar Custom Meta Boxes: paineis
 */
function metabase_custom_box_panels()
{
    add_meta_box(
        'paineis_box',
        'Metabase',
        'metabase_custom_box_panels_html',
        'panels'
    );
}

function metabase_custom_box_panels_html($post)
{
    $url = get_post_meta($post->ID, '_metabase_painel_url',  true);
    $panelId = get_post_meta($post->ID, '_metabase_painel_id',  true);
    wp_nonce_field('metabase_painel_save', 'metabase_painel_nonce');
?>
    <label for="metabase_painel_id"><strong>ID do painel (para uso com o plugin metabase-embed):</strong></label>
    <input name="metabase_painel_id" type="number" min="1" class="large-text" id="metabase_painel_id" value="<?php echo esc_attr($panelId); ?>">
    <label for="metabase_painel_url">URL do painel público (será usado caso o ID do painel esteja vazio):</label>
    <input name="metabase_painel_url" type="text" class="large-text" id="metabase_painel_url" value="<?php echo esc_attr($url); ?>">
<?php
}

add_action('add_meta_boxes', 'metabase_custom_box_panels');

function metabase_custom_box_panels_save($post_id)
{
    if (!isset($_POST['metabase_painel_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['metabase_painel_nonce'])), 'metabase_painel_save')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    if (array_key_exists('metabase_painel_url', $_POST)) {
        update_post_meta(
            $post_id,
            '_metabase_painel_url',
            esc_url_raw(wp_unslash($_POST['metabase_painel_url']))
        );
    }

    if (array_key_exists('metabase_painel_id', $_POST)) {
        $panelId = absint($_POST['metabase_painel_id']);
        update_post_meta(
            $post_id,
            '_metabase_painel_id',
            $panelId > 0 ? $panelId : ''
        );
    }
}
add_action('save_post', 'metabase_custom_box_panels_save');


/**
 * Shortcode para exibir as abas de grupos
 */
function metabase_tabs_shortcode($atts = [], $content = null)
{
    if ($content) {
        $short_codes = trim(strip_tags($content));
        $short_codes = explode("\n", $short_codes);
        $short_codes = array_filter($short_codes, function ($valor) {
            return !empty($valor);
        });

        $tabs  = '<div id="metabase-tabs" class="metabase-tabs hide">';
        $tabs .= '<ul>';

        foreach ($short_codes as $index => $item) {
            $shortcode_type = preg_match('/^\[(\w+-?\w+)/', $item, $matches) ? $matches[1] : '';
            $slug = preg_match('/slug="([^"]+)"/', $item, $matches) ? $matches[1] : '';

            if ($shortcode_type === 'panel') {
                $args = array(
                    'name'        => $slug,
                    'post_type'   => 'panels',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                );
                $found = get_posts($args);
                $term  = $found ? $found[0]->post_title : '';
            } else {
                $found = get_term_by('slug', $slug, 'panels_group');
                $term  = $found ? $found->name : '';
            }
            $tab_name = $term ? $term : 'Slug não encontrado';
            $tabs .= '<li><a href="#tab-' . $index . '">' . esc_html($tab_name) . '</a></li>';
        }

        $tabs .= '</ul>';

        foreach ($short_codes as $index => $item) {
            $tabs .=  '<div id="tab-' . $index . '">' . $item . '</div>';
        }

        $tabs .= '</div>';
        $tabs .= '<script>jQuery(document).ready(function($){$("#metabase-tabs").tabs().removeClass("hide");})</script>';        
        $tabs = do_shortcode($tabs);
    } else {
        $tabs = "Não foi encontrado o conteúdo. Use [metabase-tabs] [panel-group slug=\"grupo1\"] [panel-group slug=\"grupo2\"] [/metabase-tabs]";
    }

    return $tabs;
}

/**
 * Shortcode para exibir os paineis do grupo em abas
 */
function metabase_panel_group_shortcode($atts = [], $content = null, $tag = '')
{
    $atts = array_change_key_case((array) $atts, CASE_LOWER);
    $pg_atts = shortcode_atts(
        array(
            'slug' => 'sem-categoria',
        ),
        $atts,
        $tag
    );

    $args = array(
        'post_type'      => 'panels',
        'posts_per_page' => 6,
        'tax_query'      => array(
            array(
                'taxonomy'  => 'panels_group',
                'field'     => 'slug',
                'terms'     => $pg_atts['slug'],
            ),
        ),
        'order'         => 'ASC',
        'order_by'      => 'ID',
    );
    $loop = new WP_Query($args);
    $group = array();
    while ($loop->have_posts()) {
        $loop->the_post();
        $group[] = array(
            'title' => get_the_title(),
            'url' => get_post_meta(get_the_ID(), '_metabase_painel_url',  true),
            'id' => get_post_meta(get_the_ID(), '_metabase_painel_id',  true),
            'content' => get_the_content(),
            'slug' => get_post_field('post_name')
        );
    }
    wp_reset_postdata();

    if (!empty($group)) {
        $titles  = '';
        $panels  = '';
        $groupId = metabase_dom_id($pg_atts['slug']);

        foreach ($group as $index => $item) {
            $dialogId = metabase_dom_id(str_replace('-', '', $item['slug']));

            $titles .= '<li><a href="#' . $groupId . '-' . $index . '">' . esc_html($item['title']) . '</a></li>';

            $panels .= '<div id="' . $groupId . '-' . $index . '">';
            $panels .= metabase_get_dialog_content($dialogId, $item['content'], $item['title']);
            $panels .= metabase_get_panel_iframe($item['id'], $item['url'], $dialogId); 
            $panels .= '</div>';
        }

        $tabs  = '<div id="metabase-subtabs-' . $groupId . '" class="metabase-subtabs">';
        $tabs .= '<ul>';
        $tabs .= $titles;
        $tabs .= '</ul>';
        $tabs .= $panels;
        $tabs .= '</div>';
        $tabs .= '<script>jQuery(document).ready(function($){$("#metabase-subtabs-' . $groupId . '").tabs();})</script>';
    } else {
        $tabs = 'Não foi encontrado nenhum painel no grupo ' . esc_html($pg_atts['slug']) . '.';
    }

    return $tabs;
}

/**
 * Shortcode para exibir um painel
 */
function metabase_panel_shortcode($atts = [], $content = null, $tag = '')
{
    $atts = array_change_key_case((array) $atts, CASE_LOWER);
    $pg_atts = shortcode_atts(
        array(
            'slug' => 'default',
        ),
        $atts,
        $tag
    );

    $args = array(
        'name'        => $pg_atts['slug'],
        'post_type'   => 'panels',
        'post_status' => 'publish',
        'numberposts' => 1,
    );

    $found = get_posts($args);
    $posts = $found ? $found[0] : null;

    if ($posts) {
        $panel_url = get_post_meta($posts->ID, '_metabase_painel_url', true);
        $panel_id  = get_post_meta($posts->ID, '_metabase_painel_id', true);
        $dialogId  = metabase_dom_id(str_replace('-', '', $pg_atts['slug']));
        $panel     = metabase_get_dialog_content($dialogId, $posts->post_content, $posts->post_title);
        $panel    .= metabase_get_panel_iframe($panel_id, $panel_url, $dialogId);        
    } else
        $panel = 'Não foi encontrado nenhum painel com o slug ' . esc_html($pg_atts['slug']) . '.';

    return $panel;
}

/**
 * Mantém apenas caracteres seguros para uso em IDs do HTML e seletores JS.
 */
function metabase_dom_id($value)
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value);
}

function metabase_get_dialog_content($dialogId, $content, $panelName)
{
    if (!$dialogId)
        return '';
    
    $html = '';
    
    if ($content) {
        $html .= '<dialog id="' . $dialogId . '">';
        $html .= '<article>' . wp_kses_post($content) . '</article>';
        $html .= '<form method="dialog"><button class="button" title="Pressione a tecla ESC para fechar">Fechar</button></form></dialog>';
    }

    $html .= '<div class="button_set">';
    
    //botão tela cheia
    $html .= '<button class="button float purple" onclick="showFullScreen(\'i-'. $dialogId .'\')">';
    $html .= '<img src="' . esc_url(plugins_url('assets/icon-expand.svg', __FILE__)) . '">';
    $html .= '<span> Tela Cheia</span></button>';

    //botão sobre o painel
    if($content) {
        $html .= '<button class="button float" onclick="document.getElementById(\'' . $dialogId . '\').showModal()">';
        $html .= '<img src="' . esc_url(plugins_url('assets/icon-info.svg', __FILE__)) . '">';
        $html .= '<span> Sobre o Painel</span></button>';
    }

    //botão reportar problema
    // $html .= '<button class="button float accent-dark" onclick="showReportDialog(\''. $panelName .'\')">';
    // $html .= '<img src="' . plugins_url('assets/icon-megafone.svg', __FILE__) . '">';
    // $html .= '<span> Relatar Problema</span></button>';

    $html .= '</div>';

    return $html;
}

function metabase_get_panel_iframe($panelId, $panelUrl, $panelName) {
    $panelId   = absint($panelId);
    $panelName = metabase_dom_id($panelName);

    if ($panelId >= 1)
        return  do_shortcode("[metabase-embed id=$panelId height=100% border=false name=i-$panelName ]");
    elseif ($panelUrl) 
        return '<iframe id="i-'. esc_attr($panelName) .'" src="' . esc_url($panelUrl) . '" frameborder="0" width="100%" height="100%" class="lazyload"></iframe>';
    else
        return '<p>Não foi cadastrado a URL pública nem o ID do painel</p>';
}


/**
 * Filtro no cadastro de usuário
 */

if ( ! function_exists( 'metabase_show_user_field' )) :
    // Adicionar campo personalizado ao perfil do usuário
    function metabase_show_user_field($user) { 
        if (current_user_can('edit_users')) {?>
            <h3>Metabase Embed</h3>
            <table class="form-table">
                <tr>
                    <th><label for="user_filter">Filtro</label></th>
                    <td>
                        <textarea name="user_filter" id="user_filter" rows="5" cols="30" placeholder='"centro": [ "CCAE" ]'><?php echo esc_textarea(get_the_author_meta('user_filter', $user->ID)); ?></textarea><br />
                        <span class="description">Informe os filtros no formato JSON que deverão ser aplicados a todos os paineis acessados por este usuário.</span>
                    </td>
                </tr>
            </table>
            <?php 
        }
    }
endif;

add_action('show_user_profile', 'metabase_show_user_field');
add_action('edit_user_profile', 'metabase_show_user_field');

if ( ! function_exists( 'metabase_save_user_field' )) :
    // Salvar o campo personalizado no perfil do usuário
    // Apenas quem gerencia usuários pode alterar o filtro (inclusive o próprio)
    function metabase_save_user_field($user_id) {
        if (!current_user_can('edit_users') || !current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (!isset($_POST['user_filter'])) {
            return false;
        }

        $filter = trim(wp_unslash($_POST['user_filter']));

        if ($filter === '') {
            delete_user_meta($user_id, 'user_filter');
            return true;
        }

        $decoded = metabase_parse_user_filter($filter);
        if ($decoded === null) {
            return false; // erro já reportado em metabase_validate_user_field
        }

        update_user_meta($user_id, 'user_filter', wp_json_encode($decoded));
    }
endif;

/**
 * Converte o filtro do usuário em array. Aceita o JSON com ou sem as chaves externas.
 * Retorna null se o conteúdo não for um objeto JSON válido.
 */
function metabase_parse_user_filter($filter)
{
    $filter = trim((string) $filter);

    if (substr($filter, 0, 1) !== '{') {
        $filter = '{' . $filter . '}';
    }

    $decoded = json_decode($filter, true);

    return is_array($decoded) ? $decoded : null;
}

function metabase_validate_user_field($errors, $update, $user)
{
    if (!current_user_can('edit_users') || !isset($_POST['user_filter'])) {
        return;
    }

    $filter = trim(wp_unslash($_POST['user_filter']));

    if ($filter !== '' && metabase_parse_user_filter($filter) === null) {
        $errors->add('user_filter', '<strong>Erro:</strong> o filtro do Metabase não é um JSON válido.');
    }
}
add_action('user_profile_update_errors', 'metabase_validate_user_field', 10, 3);

add_action('personal_options_update', 'metabase_save_user_field');
add_action('edit_user_profile_update', 'metabase_save_user_field');


/**
 * Local central para criar todos os shortcodes.
 */
function metabase_shortcodes_init()
{
    add_shortcode('metabase-tabs', 'metabase_tabs_shortcode');
    add_shortcode('panel-group', 'metabase_panel_group_shortcode');
    add_shortcode('panel', 'metabase_panel_shortcode');
}
add_action('init', 'metabase_shortcodes_init');


/**
 * carregar os scripts
 */
function metabase_load_plugin_scripts()
{
    wp_enqueue_style('metabase-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');

    wp_enqueue_script('jquery-ui-tabs', '', array('jquery'));  
    wp_enqueue_script('metabase-utils', plugin_dir_url(__FILE__) . 'assets/js/utils.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'metabase_load_plugin_scripts');
