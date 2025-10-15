/**
 * Koncerto Impulsus Framework
 */

/**
 * KoncertoController
 * Connect html element to JavaScript
 *
 * @param {Node} element
 */
function KoncertoController(element)
{
    var targets = {};
    element.querySelectorAll('[data-target]').forEach(function (target) {
        targets[target.getAttribute('data-target')] = target;
    });
    element.setAttribute('data-bind', 'true');

    var controllerName = new String(element.getAttribute('data-controller')).toLowerCase();
    if (controllerName in KoncertoImpulsus.controllers) {
        setTimeout(function() {
            element.controller.default = KoncertoImpulsus.controllers[controllerName];
            element.controller.default(element.controller);
        }, 100);
    } else {
        var currentPath = new String(location.href);
        var parts = currentPath.split('/');
        if ('' !== parts[parts.length - 1]) {
            parts.pop();
            currentPath = parts.join('/') + '/';
        }
        if (!currentPath.endsWith('/_controller/')) {
            currentPath += '/_controller/';
        }
        KoncertoImpulsus.fetch(currentPath + controllerName + '.js', {
            source: element
        }, function(response, source) {
            source.controller.default = eval('(function(controller) { ' + response.responseText + ' });')(source.controller);
            KoncertoImpulsus.controllers[controllerName] = source.controller.default;
            source.controller.default(source.controller);
        });
    }

    var controller = {
        element,
        targets,
        default: null,
        on: function(method, callback) {
            window.addEventListener('message', function(event) {
                if ('object' !== typeof event.data) {
                    return;
                }
                if (!('id' in event.data)) {
                    return;
                }
                if (!('controller' in event.data)) {
                    return;
                }
                if (!('method' in event.data)) {
                    return;
                }
                if (method !== event.data.method) {
                    return;
                }
                var element = this.document.querySelector('[data-id=' + event.data.id + ']');
                element.removeAttribute('data-id');
                callback(element.controller);
            });
        }
    };

    var actions = element.querySelectorAll('[data-action]');
    actions.forEach(function (element) {
        if (!element.hasAttribute('data-bind')) {
            element.setAttribute('data-bind', 'true');
            var parts = element.getAttribute('data-action').split('#');
            var controller = parts[0];
            parts = controller.split('->');
            controller = 1 === parts.length ? parts[0] : parts[1];
            var action = 1 === parts.length ? 'click' : parts[0];
            element.addEventListener(action, function(event) {
                var el = event.target.hasAttribute('data-action') ? event.target : event.target.closes('[data-action]');
                var parts = new String(el.getAttribute('data-action')).split('#');
                var controller = parts[0];
                var method = 1 === parts.length ? 'default' : parts[1];
                parts = controller.split('->');
                controller = 1 === parts.length ? parts[0] : parts[1];
                var element = el.closest('[data-controller=' + controller + ']');
                if (null === element) {
                    console.error('Controller ' + controller + ' not found. Please check that target is inside controller.');
                    return;
                }
                element.setAttribute('data-id', controller + '-' + (new Date()).getTime());
                window.postMessage({
                    id: element.getAttribute('data-id'),
                    controller,
                    method
                });
            });
        }
    });

    return controller;
}

window.KoncertoController = KoncertoController;
/**
 * KoncertoFrame
 * Creates a dynamic frame/area
 *
 * @param {Node} section
 */
function KoncertoFrame(section)
{
    var id = new String(section.hasAttribute('id') ? section.getAttribute('id') : 'frame-' + new Date().getTime());
    section.setAttribute('id', id);
    KoncertoImpulsus.history.push({ frame: id, href: location.href, title: document.title, html: section.innerHTML });

    var frame = {
        id,
        section
    };

    var links = document.querySelectorAll('a[href]');
    links.forEach(function (link) {
        if (!link.hasAttribute('data-frame')) {
            link.setAttribute('data-frame', frame.id);
        }
        link.addEventListener('click', function(event) {
            KoncertoImpulsus.fetch(event.target.href, false, function (response) {
                var id = event.target.getAttribute('data-frame');
                var frame = document.getElementById(id);
                frame.setAttribute('data-href', response.responseURL);
                var html = document.createElement('html');
                html.innerHTML = response.responseText;
                var title = html.querySelector('head > title');
                if (null !== title) {
                    document.title = title.innerText;
                }
                var section = html.querySelector('section#' + id)
                if (null === section) {
                    console.info('No section for frame id ' + id + ' found, loading default section');
                    section = html.querySelector('section')
                }
                if (null === section) {
                    console.info('No section found, loading whole content');
                    section = html;
                }
                frame.innerHTML = section.innerHTML;
                if (KoncertoImpulsus.location !== response.responseURL) {
                    history.pushState(null, '', response.responseURL);
                    KoncertoImpulsus.history.unshift({ frame: id, href: response.responseURL, title: document.title, html: frame.innerHTML });
                    KoncertoImpulsus.location = response.responseURL;
                }
            });
            event.preventDefault();
            event.stopPropagation();
        });
    });

    return frame;
}

window.KoncertoFrame = KoncertoFrame;
/**
 * Koncerto Impulsus main class
 */
var KoncertoImpulsus = {
    /**
     * Controllers cache
     */
    controllers: {},

    /**
     * Frame navigation history
     *
     */
    history: [],

    /**
     * Current location
     */
    location: null,

    /**
     * Flag to avoid concurrent operations
     */
    working: false,

    /**
     * Auto-loaded counter for autoload callback
     */
    autoloaded: 0,

    /**
     * Temporary autoload callback function
     */
    autoloadCallback: null,

    /**
     * Load classes
     *
     * @param {function} callback
     * @return {void}
     */
    autoload: function(callback)
    {
        KoncertoImpulsus.autoloaded = 0;
        KoncertoImpulsus.autoloadCallback = callback;

        var classes = [
            'KoncertoFrame',
            'KoncertoController'
        ];

        var scripts = [];
        classes.forEach(function(className) {
            if (!(className in window)) {
                var currentPath = KoncertoImpulsus.scriptPath('KoncertoImpulsus.js');
                var script = document.createElement('script');
                script.setAttribute('data-src', currentPath + className + '.js');
                script.addEventListener('load', function(event) {
                    KoncertoImpulsus.autoloaded++;
                    var script = event.target;
                    var total = script.getAttribute('data-scripts');
                    script.removeAttribute('data-scripts');
                    if (KoncertoImpulsus.autoloaded >= total) {
                        KoncertoImpulsus.autoloadCallback();
                        KoncertoImpulsus.autoloadCallback = null;
                    }
                });
                scripts.push(script);
            }
        });

        scripts.forEach(function (script) {
            script.setAttribute('data-scripts', new String(scripts.length));
            script.setAttribute('src', new String(script.getAttribute('data-src')));
            script.removeAttribute('data-src');
            document.querySelector('head').appendChild(script);
        });

        if (0 === scripts.length) {
            callback();
        }
    },

    /**
     * Initialize Impulsus framework
     *
     * @return {void}
     */
    init: function()
    {
        KoncertoImpulsus.autoload(function() {
            KoncertoImpulsus.location = location.href;
            KoncertoImpulsus.observe('://', function() {
                var history = KoncertoImpulsus.history.filter(function (entry, index) {
                    // @todo - true history, clear (KoncertoImpulsus.history[index] = null; ?)
                    return KoncertoImpulsus.location === entry.href;
                });
                KoncertoImpulsus.history = KoncertoImpulsus.history.filter(function (entry) {
                    return null !== entry;
                });
                history.forEach(function(entry) {
                    document.title = entry.title;
                    var frame = document.getElementById(entry.frame);
                    frame.innerHTML = entry.html;
                    frame.removeAttribute('data-impulsus');
                });
                KoncertoImpulsus.init();
            });

            KoncertoImpulsus.observe('section', function(sections) {
                sections.forEach(function (section) {
                    if (!section.hasAttribute('data-impulsus')) {
                        section.setAttribute('data-impulsus', 'true');
                    }
                    if ('false' !== section.getAttribute('data-impulsus')) {
                        section.frame = KoncertoFrame(section);
                    }
                });
            });

            KoncertoImpulsus.observe('[data-controller]', function(elements) {
                elements.forEach(function (element) {
                    if (!element.hasAttribute('data-bind')) {
                        element.controller = KoncertoController(element);
                    }
                });
            });
        });
    },

    /**
     * Observe existing/new elements using selector
     * and MutationObserver / setInterval
     *
     * @param {string} selector
     * @param {function} callback
     * @return {void}
     */
    observe: function(selector, callback)
    {
        // URL change observer to restore frames content
        if ('://' === selector) {
            setInterval(function() {
                KoncertoImpulsus.waitUntil(function() {
                    if (KoncertoImpulsus.location !== window.location.href) {
                        KoncertoImpulsus.location = window.location.href;
                        console.info('Navigation to ' + KoncertoImpulsus.location);
                        callback();
                    }
                });
            }, 100);

            return;
        }

        if ('#' === selector) {
            // @todo - create a hash change observer
            return;
        }

        var elements = document.querySelectorAll(selector);
        if ('section' === selector) {
            var sections = KoncertoImpulsus.getSections(elements);
            callback(sections);
        } else {
            callback(elements);
        }

        if ('undefined' !== typeof MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function (mutation) {
                    if ('section' === selector) {
                        var sections = KoncertoImpulsus.getSections(mutation.addedNodes);
                        callback(sections);
                    } else {
                        var elements = document.querySelectorAll(selector);
                        callback(elements);
                    }
                });
            });
            observer.observe(document, { attributes: true, childList: true, subtree: true });
            return;
        }

        // Fallback for older browsers
        setInterval(function() {
            KoncertoImpulsus.waitUntil(function() {
                var elements = document.querySelectorAll(selector);
                var sections = KoncertoImpulsus.getSections(elements);
                callback(sections);
            })
        }, 100);
    },

    /**
     * Get unbound sections from NodeList
     *
     * @param {NodeList} nodes
     * @return {Node[]}
     */
    getSections: function(nodes) {
        var nodesArray = Array.prototype.slice.call(nodes);

        return nodesArray.filter(function(node) {
            var name = new String(node.nodeName);
            return 'section' === name.toLowerCase() && !node.hasAttribute('data-impulsus');
        });
    },

    /**
     * Performs a blocking operation, even in setInterval
     *
     * @param {function} callback
     */
    waitUntil: function(callback)
    {
        if (KoncertoImpulsus.working) {
            setTimeout(function() {
                callback();
            }, 100);
            return;
        }
        KoncertoImpulsus.working = true;
        callback();
        KoncertoImpulsus.working = false;
    },

    /**
     * Performs XHR request and return result to callback
     *
     * @param {string} url
     * @param {object} options
     * @param {function} callback
     */
    fetch: function(url, options, callback)
    {
        options = options || {
            method: 'GET',
            source: null
        };

        const xhr = new XMLHttpRequest();
        xhr.open(options.method || 'GET', url);
        xhr.onload = function() {
            if (4 === xhr.readyState) {
                callback(xhr, options.source);
            }
        };

        xhr.send();
    },

    /**
     * Get (current) script path
     *
     * @param {string|null} scriptName
     * @return string
     */
    scriptPath: function(scriptName = null)
    {
        var src = '';
        if (('currentScript' in document) && null !== document.currentScript) {
            src = document.currentScript.src;
        } else {
            var script = document.querySelector('script[src$="/' + scriptName + '"');
            src = script ? script.src : window.location.href;
        }

        var parts = new String(src).split('/');
        parts.pop();

        return parts.join('/') + '/';
    }
};

KoncertoImpulsus.init();

window.KoncertoImpulsus = KoncertoImpulsus;
