<?php

/*
 * Plugin Name:       Metabase Embed
 * Plugin URI:        https://github.com/francoisjun/metabase-embed-wp/
 * Description:       Shortcode para incorporar dashboards do Metabase.
 * Version:           1.0.3
 * Requires PHP:	  7.1
 * Author:            François Júnior
 * Author URI:        https://github.com/francoisjun/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       metabase-embed
 * Domain Path:       /languages
 */

require 'vendor/autoload.php';

use Firebase\JWT\JWT;

function metabase_embed_activate() { 
	flush_rewrite_rules(); 
}
register_activation_hook( __FILE__, 'metabase_embed_activate');


function metabase_embed_deactivate() {
	remove_shortcode('metabase-embed');
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'metabase_embed_deactivate');


function metabase_embed_menu() {
	add_plugins_page(
		__('Configurações do Metabase Embed', 'metabase-embed'),
		__('Metabase Embed', 'metabase-embed'),
		'read',
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
	register_setting('metabase-embed-settings-group', 'metabase_site_url');
	register_setting('metabase-embed-settings-group', 'metabase_secret_key');
    	
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
	$secret_key = esc_attr(get_option('metabase_secret_key'));
	echo '<input name="metabase_secret_key" type="text" id="metabase_secret_key" class="regular-text" value="'.$secret_key.'">';
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

    $site_url   = esc_attr(get_option('metabase_site_url'));
    $secret_key = esc_attr(get_option('metabase_secret_key'));
    $payload    = [
        'resource' => ['dashboard' => intval($atts['id'])],
        'params'   => new stdClass(),
        'exp'      => time() + (10 * 60)
    ]; 

    $token      = JWT::encode($payload, $secret_key, 'HS256');
    $iframeUrl  = $site_url . "/embed/dashboard/" . $token . metabase_embed_get_view_params($atts);

	$iframeId    = ($atts['name'] != null) ? 'id="'. $atts['name'] . '"' : '';
	$iframeStyle = ($atts['style'] != null) ? 'class="'. $atts['style'] . '"' : '';
	$iframeSrc   = ($atts['lazy']) ? 'src': 'data-src';

	return '<iframe '.$iframeId.' '.$iframeStyle.' '.$iframeSrc.'="'.$iframeUrl.'" frameborder="0" width="'.$atts['width'].'" height="'.$atts['height'].'"></iframe>';
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
		array_push($selected_params, $atts['filter']);
	}

	return '#' . implode('&', $selected_params);
}
