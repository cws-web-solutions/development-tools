import CwsDevelopmentToolsApiService from './service/cws-development-tools-api.service';
import './component/cws-development-tools-settings-icon';
import './page/cws-development-tools-index';
import './page/cws-development-tools-documentation';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Shopware.Service().register('cwsDevelopmentToolsApiService', () => {
    return new CwsDevelopmentToolsApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    );
});

Module.register('cws-development-tools', {
    type: 'plugin',
    name: 'cws-development-tools',
    title: 'cws-development-tools.general.mainMenuItemGeneral',
    description: 'cws-development-tools.general.descriptionTextModule',
    color: '#1f6feb',
    icon: 'regular-cog',
    routePrefixPath: 'cws/development-tools',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'cws-development-tools-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.plugins',
                privilege: 'system.plugin_maintain',
            },
        },
        documentation: {
            component: 'cws-development-tools-documentation',
            path: 'documentation',
            meta: {
                parentPath: 'cws.development.tools.index',
                privilege: 'system.plugin_maintain',
            },
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'cws.development.tools.index',
        iconComponent: 'cws-development-tools-settings-icon',
        privilege: 'system.plugin_maintain',
    },
});
