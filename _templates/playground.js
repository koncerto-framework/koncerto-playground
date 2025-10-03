window.currentProject = '';
window.currentDirectory = '';
window.currentFile = '';
window.newProject = function() {
    var projectName = prompt('Project name');
    if ('' === (projectName ?? '').trim()) {
        return;
    }
    document.querySelector('.panel-heading').innerText = projectName;
    var menu = document.querySelector('.menu');
    menu.innerHTML = '';
    newDirectory('_cache');
    newDirectory('_controller');
    newDirectory('_templates');
    window.currentProject = projectName;
}
window.openProject = function() {
    var projectName = prompt('Project name');
    if ('' === (projectName ?? '').trim()) {
        return;
    }
    document.querySelector('.panel-heading').innerText = projectName;
    var menu = document.querySelector('.menu');
    menu.innerHTML = '';
    newDirectory('_cache');
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
}
window.newDirectory = function(name) {
    if (undefined === name) {
        name = prompt('Directory name');
    }
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
}
window.setCurrentDirectory = function(el) {
    var name = el.getAttribute('data-directory');
    window.currentDirectory = name;
    var active = document.querySelector('p.menu-label.has-text-weight-bold');
    if (null !== active) {
        active.classList.remove('has-text-weight-bold');
    }
    el.classList.add('has-text-weight-bold');
}
window.newFile = function(name, content) {
    if ('' === window.currentDirectory) {
        return;
    }
    var menu = document.querySelector(`[data-parent="${window.currentDirectory}"]`);
    if (null === menu) {
        return;
    }
    if (undefined === name) {
        name = prompt('File name');
    }
    if ('' === (name ?? '').trim()) {
        return;
    }
    var li = document.createElement('li');
    menu.appendChild(li);
    var a = document.createElement('a');
    a.setAttribute('href', `#${window.currentDirectory}/${name}`);
    a.setAttribute('data-file', `${window.currentDirectory}/${name}`);
    a.setAttribute('class', 'is-active');
    a.setAttribute('onclick', 'loadFile(this); this.classList.add(\'is-active\');');
    a.innerText = name;
    li.appendChild(a);
    if (undefined === content) {
        window.localStorage.setItem(`projects/${window.currentProject}/${window.currentDirectory}/${name}`, '');
    }
    window.loadFile(a);
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
    window.open(`run.html#/${window.currentProject}`);
}
