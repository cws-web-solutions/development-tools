import template from './sw-extension-card-base.html.twig';

const { Component } = Shopware;

Component.override('sw-extension-card-base', {
    template,

    computed: {
        isCwsDevelopmentToolsExtension() {
            return ['CwsDevelopmentTools', 'cws/development-tools'].includes(this.extension && this.extension.name);
        },
    },
});
