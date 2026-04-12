import template from './cws-development-tools-documentation.html.twig';
import './cws-development-tools-documentation.scss';

const { Component, Mixin } = Shopware;

Component.register('cws-development-tools-documentation', {
    template,

    inject: ['cwsDevelopmentToolsApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            state: null,
        };
    },

    computed: {
        documentation() {
            return this.state?.documentation ?? { title: '', intro: [], features: [] };
        },

        features() {
            return this.documentation.features ?? [];
        },
    },

    created() {
        this.loadState();
    },

    methods: {
        goToSettings() {
            this.$router.push({ name: 'cws.development.tools.index' });
        },

        goToPlugins() {
            this.$router.push({ name: 'sw.extension.my-extensions.listing.app' });
        },

        async loadState() {
            this.isLoading = true;

            try {
                this.state = await this.cwsDevelopmentToolsApiService.loadState();
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.message || error?.message,
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
