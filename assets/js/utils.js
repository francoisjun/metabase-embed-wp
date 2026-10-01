jQuery(document).ready(($) => {
    window.showFullScreen = function(panel) {
        const element = document.getElementById(panel);
        if (!document.fullscreenElement) {
            element.requestFullscreen().catch((err) => {
                console.error(`Erro ao habilitar modo fullscreen: ${err.message} (${err.name})`);
            });
        } else {
            document.exitFullscreen();
        }
    }
});