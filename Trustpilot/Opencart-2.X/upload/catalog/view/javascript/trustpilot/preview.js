var w = document.createElement("script");
w.type = "text/javascript";
w.src = trustpilot_widget_script_url;
w.async = true;
document.head.appendChild(w);

function inIframe () {
    try {
        return window.self !== window.top;
    } catch (e) {
        return false;
    }
}

if (inIframe()) {
    window.addEventListener('message', function(e) {
        var adminOrign = new URL(window.location).hostname;
        if (!e.data || e.origin.indexOf(adminOrign) === -1) {
            return;
        }
        if (typeof TrustpilotPreview !== 'undefined') {
            if (typeof e.data === 'string' && e.data === 'submit') {
                TrustpilotPreview.sendTrustboxes();
            } else {
                jsonData = JSON.parse(e.data);
                if (jsonData.trustbox) {
                    TrustpilotPreview.setSettings(jsonData.trustbox);
                } else if (jsonData.customised) {
                    TrustpilotPreview.updateActive(jsonData.customised);
                }
            }
        } else {
            var p = document.createElement("script");
            p.type = "text/javascript";
            p.onload = function () {
                const iFrame = e.source.parent.document.getElementById('configuration_iframe').contentWindow;
                TrustpilotPreview.init([trustpilot_preview_css_url], JSON.parse(e.data), iFrame, e.source);
            };
            p.src = trustpilot_preview_script_url;
            document.head.appendChild(p);
        }
    });
}