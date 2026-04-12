import template from './sw-desktop.html.twig';

const { Component } = Shopware;

Component.override('sw-desktop', {
    template,
});
