/**
 * Playground controller
 */
return function(controller) {
    window.currentProject = '';
    window.currentDirectory = '';
    window.currentFile = '';

    controller.on('burger', function (controller) {
        controller.targets.menu.classList.toggle('is-active');
    });

    controller.on('newProject', function (controller) {
        var projectName = prompt('Project name');
        if ('' === (projectName ?? '').trim()) {
            return;
        }
        document.querySelector('.panel-heading').innerText = projectName;
        var menu = document.querySelector('.menu');
        menu.innerHTML = '';
        newDirectory('_controller');
        newDirectory('_templates');
        window.currentProject = projectName;
    });
}
