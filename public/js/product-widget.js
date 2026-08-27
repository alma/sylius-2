/*
 * Product-page widget bootstrap.
 *
 * The widget lives inside the Sylius 2.0 product "summary" block, a Symfony
 * UX Live Component: every quantity or variant change triggers an AJAX
 * re-render that morphs the DOM back to the server HTML and never re-executes
 * inline scripts. The container carries `data-live-ignore` so the morph
 * neither wipes nor removes the client-rendered markup; the sibling JSON
 * config is left morphable and receives the newly selected variant's price,
 * so this script only has to re-read it and re-mount when the amount changed.
 *
 * symfony/ux-live-component no longer dispatches a `live:render` DOM event
 * (removed upstream, see its CHANGELOG); the stable re-render signal is each
 * component's `render:finished` hook, reachable through the bubbling
 * `live:connect` event.
 *
 * Re-calling `widgets.add()` on the same container IS the refresh API of
 * @alma/widgets v4 (toolkit references/widget-cheatsheet.md § Re-rendering),
 * but each call rebuilds the widget DOM from scratch — visible as a flicker.
 * Renders are therefore memoized on the (config, quantity) pair: a
 * `render:finished` that did not change the purchase amount re-mounts
 * nothing. The quantity input is read through document-level event delegation
 * because the form controls themselves are replaced on every morph.
 */
(function () {
    'use strict';

    if (window.almaProductWidget) {
        return;
    }

    var RENDER_DEBOUNCE_MS = 150;
    var widgetsController = null;
    var widgetsControllerKey = null;
    var lastRenderKey = null;
    var renderTimer = null;

    function readConfig() {
        var configScriptTag = document.getElementById('alma-product-widget-config');
        if (!configScriptTag) {
            return null;
        }
        var config;
        try {
            config = JSON.parse(configScriptTag.textContent || '{}');
        } catch (e) {
            return null;
        }
        if (!config || !config.merchantId || typeof config.unitPrice !== 'number') {
            return null;
        }
        return config;
    }

    function readQuantity() {
        var quantityInput = document.querySelector('input[name$="[cartItem][quantity]"]');
        if (!quantityInput) {
            return 1;
        }
        var parsedQuantity = parseInt(quantityInput.value, 10);
        return (isNaN(parsedQuantity) || parsedQuantity <= 0) ? 1 : parsedQuantity;
    }

    function render() {
        if (typeof Alma === 'undefined' || !Alma.Widgets) {
            return;
        }
        var widgetContainer = document.getElementById('alma-product-widget');
        if (!widgetContainer) {
            return;
        }
        var config = readConfig();
        if (config === null) {
            /* The morph never removes the ignored container, so when the
               server stops emitting a config (no priced variant selected)
               the stale widget has to be cleared here. */
            widgetContainer.innerHTML = '';
            delete widgetContainer.dataset.almaMounted;
            lastRenderKey = null;
            return;
        }
        var renderKey = [
            config.merchantId,
            config.mode,
            config.locale || '',
            config.unitPrice,
            readQuantity(),
            JSON.stringify(config.plans || []),
        ].join('|');
        if (renderKey === lastRenderKey && widgetContainer.dataset.almaMounted === '1') {
            return;
        }
        var nextControllerKey = config.merchantId + '|' + config.mode;
        if (widgetsController === null || widgetsControllerKey !== nextControllerKey) {
            widgetsController = Alma.Widgets.initialize(config.merchantId, Alma.ApiMode[config.mode] || Alma.ApiMode.TEST);
            widgetsControllerKey = nextControllerKey;
        }
        var widgetOptions = {
            container: '#alma-product-widget',
            purchaseAmount: config.unitPrice * readQuantity(),
            plans: config.plans || [],
            hideIfNotEligible: true,
        };
        if (config.locale) {
            widgetOptions.locale = config.locale;
        }
        widgetsController.add(Alma.Widgets.PaymentPlans, widgetOptions);
        widgetContainer.dataset.almaMounted = '1';
        lastRenderKey = renderKey;
    }

    function scheduleRender() {
        window.clearTimeout(renderTimer);
        renderTimer = window.setTimeout(render, RENDER_DEBOUNCE_MS);
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
        liveComponent.on('render:finished', scheduleRender);
    });

    document.addEventListener('input', function (event) {
        var changedField = event.target;
        if (changedField && typeof changedField.name === 'string' && /\[cartItem\]\[quantity\]$/.test(changedField.name)) {
            scheduleRender();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            renderWhenAlmaReady(100);
        });
    } else {
        renderWhenAlmaReady(100);
    }

    window.almaProductWidget = { render: render };
})();
