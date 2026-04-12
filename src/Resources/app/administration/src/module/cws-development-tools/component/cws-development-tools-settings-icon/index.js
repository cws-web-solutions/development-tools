import template from './cws-development-tools-settings-icon.html.twig';
import './cws-development-tools-settings-icon.scss';
import pluginIcon from '../../../../../../../config/plugin.png';

const { Component } = Shopware;

Component.register('cws-development-tools-settings-icon', {
    template,

    data() {
        return {
            pluginIcon,
        };
    },
});
