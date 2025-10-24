/**
 * Home controller
 */
return function(controller) {
    setTimeout(function() {
        controller.targets.doc.innerHTML = (new showdown.Converter()).makeHtml(controller.targets.doc.innerText);

        document.querySelectorAll('.content a[href^=http]').forEach(function(a) {
            a.setAttribute('target', '_blank');
        });
    }, 1000);
}
