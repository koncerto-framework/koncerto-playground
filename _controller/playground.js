/**
 * Playground controller
 */
return function(controller) {
    window.currentProject = '';
    window.currentDirectory = '';
    window.currentFile = '';
    window.fileModified = 0;

    // @todo - actions, triggers, listeners, etc
    window.setCurrentDirectory = function(el) {
        var name = el.getAttribute('data-directory');
        window.currentDirectory = name;
        var active = document.querySelector('p.menu-label.has-text-weight-bold');
        if (null !== active) {
            active.classList.remove('has-text-weight-bold');
        }
        el.classList.add('has-text-weight-bold');
    }

    window.loadFile = function (path) {
        var el = null;
        if (!('string' === typeof path)) {
            el = path;
            path = path.getAttribute('data-file');
        }
        if (null === el) {
            el = document.querySelector(`[data-file="${path}"]`);
        }
        window.saveFile();
        path = `projects/${window.currentProject}/${path}`;
        if (path in localStorage) {
            window.editor.setValue(localStorage.getItem(path));
        } else {
            console.error(`${path} file not found`);
            window.editor.setValue('');
        }
        window.currentFile = path;
        var active = document.querySelector('.is-active');
        if (null !== active) {
            active.classList.remove('is-active');
        }
    }

    window.saveFile = function() {
        if ('' === window.currentFile) {
            return;
        }
        localStorage.setItem(window.currentFile, window.editor.getValue());
    }

    window.addEventListener('resize', function(event) {
        var parent = document.querySelector('#container').parentElement;
        window.editor.layout({ width: 0, height: 0 });
        window.requestAnimationFrame(function() {
            var rect = parent.getBoundingClientRect();
            window.editor.layout({ width: rect.width, height: rect.height });
        });
    });

    window.run = function() {
        window.open(`run/#?project=/${window.currentProject}`);
    }

    setTimeout(function() {
        var hash = location.hash.substring(1);
        var path = -1 !== hash.indexOf('?') ? hash.substring(hash.indexOf('?') + 1) : '';
        var params = new URLSearchParams(path);
        var path = params.get('project') ?? '';
        if (path.startsWith('/')) {
            var projectName = path.substring(1);
            document.querySelector('[data-action$=openProject]').click();
            setTimeout(function() {
                var list = document.getElementById('open-project-name');
                for (var i = 0 ; i < list.options.length; i++) {
                    if (projectName === list.options[i].text) {
                        list.options.selectedIndex = i;
                        break;
                    }
                }
                document.querySelector('[data-action$=openProjectConfirm]').click();
                // @todo - Open file
            }, 1000);
        }
        window.editor.getModel().onDidChangeContent(function(event) {
            window.fileModified++;
        });
    }, 2000);

    setInterval(function() {
        if (window.fileModified > 1) {
            document.querySelector('a[data-file].is-active')?.classList.add('has-background-danger');
            window.fileModified--;
            return;
        }
        if (window.fileModified > 0) {
            window.fileModified--;
            window.saveFile();
        } else {
            document.querySelector('a[data-file].is-active')?.classList.remove('has-background-danger');
        }
    }, 200);

    controller.on('burger', function (controller) {
        controller.targets.menu.classList.toggle('is-active');
    });

    controller.on('closeModal', function () {
        document.querySelector('.modal.is-active').classList.remove('is-active');
    });

    controller.on('newProject', function () {
        document.getElementById('modal-new-project').classList.add('is-active');
    });

    controller.on('newProjectConfirm', function() {
        var projectName = document.getElementById('new-project-name').value;
        if ('' === (projectName ?? '').trim()) {
            return;
        }
        document.querySelector('.panel-heading').innerText = projectName;
        var menu = document.querySelector('.menu');
        menu.innerHTML = '';
        newDirectory('_controller');
        newDirectory('_templates');
        window.currentProject = projectName;
        document.getElementById('modal-new-project').classList.remove('is-active');
    });

    controller.on('openProject', function () {
        var projects = [];
        for (var file in localStorage) {
            var parts = file.split('/');
            if ('projects' === parts[0] && parts.length >= 2) {
                projects.push(parts[1]);
            }
        }
        projects = projects.filter((value, index, array) => array.indexOf(value) === index);
        document.getElementById('open-project-name').innerText = '';
        projects.forEach(function(project) {
            var option = document.createElement('option');
            option.innerText = project;
            document.getElementById('open-project-name').appendChild(option);
        });
        document.getElementById('modal-open-project').classList.add('is-active');
    });

    controller.on('openProjectConfirm', function() {
        var projectName = document.getElementById('open-project-name').value;
        console.debug(projectName);
        if ('' === (projectName ?? '').trim()) {
            return;
        }
        document.querySelector('.panel-heading').innerText = projectName;
        var menu = document.querySelector('.menu');
        menu.innerHTML = '';
        newDirectory('_controller');
        newDirectory('_templates');
        window.currentProject = projectName;
        for (var i in window.localStorage) {
            var path = `projects/${projectName}/`;
            if (0 === i.indexOf(path)) {
                var name = i.substring(i.lastIndexOf('/') + 1);
                window.currentDirectory = i.substring(path.length, i.lastIndexOf('/'));
                window.newFile(name, window.localStorage.getItem(i));
            }
        }
        var hash = location.hash.substring(1);
        var path = -1 !== hash.indexOf('?') ? hash.substring(hash.indexOf('?') + 1) : '';
        var params = new URLSearchParams(path);
        params.set('project', '/' + projectName);
        document.getElementById('modal-open-project').classList.remove('is-active');
        document.querySelector('ul[data-parent] > li > a').click();
    });

    controller.on('newFolder', function () {
        document.getElementById('modal-new-folder').classList.add('is-active');
    });

    controller.on('newFolderConfirm', function () {
        var name = document.getElementById('new-folder-name').value;
        if ('' === (name ?? '').trim()) {
            return;
        }
        var p = document.createElement('p');
        p.setAttribute('data-directory', name);
        p.setAttribute('class', 'menu-label');
        p.setAttribute('style', 'cursor: pointer');
        p.setAttribute('onclick', 'window.setCurrentDirectory(this);')
        p.innerText = name;
        var menu = document.querySelector('.menu');
        menu.appendChild(p);
        var ul = document.createElement('ul');
        ul.setAttribute('data-parent', name);
        ul.setAttribute('class', 'menu-list');
        menu.appendChild(ul);
    });

    controller.on('newFile', function () {
        document.getElementById('modal-new-file').classList.add('is-active');
    });

    controller.on('newFileConfirm', function () {
        if ('' === window.currentDirectory) {
            return;
        }
        var menu = document.querySelector(`[data-parent="${window.currentDirectory}"]`);
        if (null === menu) {
            return;
        }
        var name = document.getElementById('new-file-name').value;
        if ('' === (name ?? '').trim()) {
            return;
        }
        var li = document.createElement('li');
        menu.appendChild(li);
        var a = document.createElement('a');
        a.setAttribute('href', `#?project=%2F${window.currentProject}&file=${window.currentDirectory}%2F${name}`);
        a.setAttribute('data-file', `${window.currentDirectory}/${name}`);
        a.setAttribute('class', 'is-active');
        a.setAttribute('onclick', 'loadFile(this); document.querySelector(\'[data-directory=\' + this.closest(\'ul\').dataset.parent + \']\').click(); this.classList.add(\'is-active\');');
        a.innerText = name;
        li.appendChild(a);
        if (undefined === content) {
            window.localStorage.setItem(`projects/${window.currentProject}/${window.currentDirectory}/${name}`, '');
        }
        window.loadFile(a);
    });
}
