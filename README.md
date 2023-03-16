# Metabase Embed
Plugin Wordpress para incorporar o Metabase

## Instalação
Baixe a versão mais recente em [github.com/francoisjun/metabase-embed-wp/releases/](https://github.com/francoisjun/metabase-embed-wp/releases/)

Na área administrativa do Wordpress, acesse a página de plugins.

Clique no botão `Adicionar novo` ao lado do título da página:

![Novo Plugin](/assets/plugins_add_new.png "Adicionar novo plugin")

Clique no botão `Enviar plugin` ao lado do título da página. Em seguida, escolha o arquivo baixado no primeiro passo. Por fim, clique no botão `Instalar agora`.

![Enviar Plugin](/assets/plugins_install.png "Enviar novo plugin")

> Não esqueça de ativar o plugin após a instalação.

## Configuração
Após a ativação do plugin, será adicionado um submenu ao menu de `Plugins`:

![Menu de Plugins](/assets/plugins_submenu.png "Submenu do Metabase Embed")

Ao acessar esse submenu, configure o plugin com a URL do servidor do Metabase bem como a chave secreta (secret key) de compartilhamento:

![Tela de configurações do Metabase Embed](/assets/config_screen.png "Configurações do Metabase Embed")

## Uso
Em um post ou página do Wordpress, adicione a shortcode `[metabase-embed <parametros>]` onde deseja que seja exibido o Dashboard.

Os parâmetros disponíveis são:
- `id`: número do dashboad a ser exibido (default: 1).
- `border`: exibe ou não uma borda ao redor do dashboard (default: true).
- `title`: exibe ou não o título do dashboard (default: true).
- `theme`: tema do dashboard (default: white). Valores possíveis: `night`, `transparent`.
- `filter`: filtros a serem passados pela URL no padrão `chave=valor` (default: null). Separe os filtros com o caracter `&`.
- `width`: largura em pixels do dashboard (default: 100%).
- `height`: altura em pixels do dashboard (default: 600).

### Exemplo
Básico:
```
[metabase-embed id=1]
```

Completo:

```
[metabase-embed id=2 width=800 height=400 border=false title=true theme=night filter="city=Florence&state=CD"]
```

![Edição de página no Wordpress](/assets/wp_page_edit.png "Inserindo o shortcode na página")

![Página do Wordpress](/assets/wp_page_rendered.png "Resultado da página")

## Desenvolvimento
Esse plugin faz uso em tempo de execução do pacote `firebase/php-jwt` para gerar o token de acesso. O arquivo de instalação do plugin já tem os pacotes incluídos.

Para testar o plugin em sem ambiente de desenvolvimento você precisará instalar os pacotes via composer.

Rode o comando `composer install` no diretório raíz do projeto para instalar as dependências. 