/*
 * Cart page widget bootstrap.
 *
 * The widget sits inside the Sylius cart form, a Symfony UX Live Component
 * re-rendered on every cart change (quantity, removal, coupon). As for the
 * product widget, the container carries `data-live-ignore` and the JSON
 * config sibling is morphed with the new cart total; this script re-reads it
 * on each component `render:finished` and re-mounts only when it changed,
 * since every `widgets.add()` rebuilds the widget DOM (visible flicker).
 */
(function () {
    'use strict';

    if (window.almaCartWidget) {
        return;
    }

    var widgetsController = null;
    var widgetsControllerKey = null;
    var lastRenderKey = null;

    function readConfig() {
        var configScriptTag = document.getElementById('alma-cart-widget-config');
        if (!configScriptTag) {
            return null;
        }
        var config;
        try {
            config = JSON.parse(configScriptTag.textContent || '{}');
        } catch (e) {
            return null;
        }
        if (!config || !config.merchantId || typeof config.amount !== 'number') {
            return null;
        }
        return config;
    }

    function render() {
        if (typeof Alma === 'undefined' || !Alma.Widgets) {
            return;
        }
        var widgetContainer = document.getElementById('alma-cart-widget');
        if (!widgetContainer) {
            return;
        }
        var config = readConfig();
        if (config === null) {
            /* The morph never removes the ignored container: clear the stale
               widget when the server stops emitting a config. */
            widgetContainer.innerHTML = '';
            delete widgetContainer.dataset.almaMounted;
            lastRenderKey = null;
            return;
        }
        var renderKey = JSON.stringify(config);
        if (renderKey === lastRenderKey && widgetContainer.dataset.almaMounted === '1') {
            return;
        }
        var nextControllerKey = config.merchantId + '|' + config.mode;
        if (widgetsController === null || widgetsControllerKey !== nextControllerKey) {
            widgetsController = Alma.Widgets.initialize(config.merchantId, Alma.ApiMode[config.mode] || Alma.ApiMode.TEST);
            widgetsControllerKey = nextControllerKey;
        }
        var widgetOptions = {
            container: '#alma-cart-widget',
            purchaseAmount: config.amount,
            plans: config.plans || [],
            hideIfNotEligible: config.hideIfNotEligible === true,
            monochrome: config.monochrome !== false,
            hideBorder: config.hideBorder === true,
            transitionDelay: config.transitionDelay,
        };
        if (config.locale) {
            widgetOptions.locale = config.locale;
        }
        widgetsController.add(Alma.Widgets.PaymentPlans, widgetOptions);
        widgetContainer.dataset.almaMounted = '1';
        lastRenderKey = renderKey;
    }

    function renderWhenAlmaReady(attemptsLeft) {
        if (typeof Alma !== 'undefined' && Alma.Widgets) {
            render();
            return;
        }
        if (attemptsLeft <= 0) {
            return;
        }
        window.setTimeout(function () {
            renderWhenAlmaReady(attemptsLeft - 1);
        }, 100);
    }

    document.addEventListener('live:connect', function (event) {
        var liveComponent = event.detail ? event.detail.component : null;
        if (!liveComponent || typeof liveComponent.on !== 'function') {
            return;
        }
        liveComponent.on('render:finished', render);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            renderWhenAlmaReady(100);
        });
    } else {
        renderWhenAlmaReady(100);
    }

    window.almaCartWidget = { render: render };
})();
