(function (wp, data) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
        return;
    }

    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var Notice = wp.components.Notice;
    var __ = wp.i18n.__;

    data = data || {};
    var languages = Array.isArray(data.languages) ? data.languages : [];
    var defaults = data.defaults || {};

    function resolved(value, fallback) {
        return !value || value === 'inherit' ? fallback : value;
    }

    function languageLabel(language, format) {
        if (format === 'code') {
            return String(language.code || '').toUpperCase();
        }
        if (format === 'english') {
            return language.english || language.code;
        }
        return language.native || language.english || language.code;
    }

    function preview(attributes) {
        var style = resolved(attributes.style, defaults.style || 'dropdown');
        var labels = resolved(attributes.labels, defaults.labels || 'native');
        var alignment = resolved(attributes.alignment, defaults.alignment || 'end');
        var previewLanguages = languages.length ? languages : [
            { code: 'en', native: 'English', english: 'English' },
            { code: 'de', native: 'Deutsch', english: 'German' },
            { code: 'fr', native: 'Français', english: 'French' }
        ];

        if (style === 'inline') {
            return el(
                'div',
                { className: 'lp-block-preview is-inline is-' + alignment },
                previewLanguages.map(function (language, index) {
                    return el(
                        'span',
                        { className: index === 0 ? 'is-active' : '', key: language.code },
                        languageLabel(language, labels)
                    );
                })
            );
        }

        return el(
            'div',
            { className: 'lp-block-preview is-dropdown is-' + alignment },
            el('span', { className: 'lp-block-current' }, languageLabel(previewLanguages[0], labels)),
            el(
                'div',
                { className: 'lp-block-menu' },
                previewLanguages.slice(1, 5).map(function (language) {
                    return el('span', { key: language.code }, languageLabel(language, labels));
                })
            )
        );
    }

    registerBlockType('localizepilot/language-switcher', {
        title: __('Language Switcher', 'localizepilot'),
        category: 'widgets',
        icon: 'translation',
        description: __('Display the LocalizePilot language switcher anywhere in your content or templates.', 'localizepilot'),
        attributes: {
            style: { type: 'string', default: 'inherit' },
            labels: { type: 'string', default: 'inherit' },
            alignment: { type: 'string', default: 'inherit' }
        },
        supports: {
            html: false,
            customClassName: true,
            anchor: true,
            spacing: { margin: true, padding: true }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps({ className: 'localizepilot-switcher-editor' });

            return el(
                wp.element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Switcher settings', 'localizepilot'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Layout', 'localizepilot'),
                            value: attributes.style,
                            options: [
                                { label: __('Use plugin setting', 'localizepilot'), value: 'inherit' },
                                { label: __('Dropdown', 'localizepilot'), value: 'dropdown' },
                                { label: __('Inline links', 'localizepilot'), value: 'inline' }
                            ],
                            onChange: function (value) { setAttributes({ style: value }); }
                        }),
                        el(SelectControl, {
                            label: __('Language labels', 'localizepilot'),
                            value: attributes.labels,
                            options: [
                                { label: __('Use plugin setting', 'localizepilot'), value: 'inherit' },
                                { label: __('Native names', 'localizepilot'), value: 'native' },
                                { label: __('English names', 'localizepilot'), value: 'english' },
                                { label: __('Language codes', 'localizepilot'), value: 'code' }
                            ],
                            onChange: function (value) { setAttributes({ labels: value }); }
                        }),
                        el(SelectControl, {
                            label: __('Alignment', 'localizepilot'),
                            value: attributes.alignment,
                            options: [
                                { label: __('Use plugin setting', 'localizepilot'), value: 'inherit' },
                                { label: __('Start', 'localizepilot'), value: 'start' },
                                { label: __('Center', 'localizepilot'), value: 'center' },
                                { label: __('End', 'localizepilot'), value: 'end' }
                            ],
                            onChange: function (value) { setAttributes({ alignment: value }); }
                        })
                    )
                ),
                el(
                    'div',
                    blockProps,
                    el('div', { className: 'lp-block-heading' },
                        el('span', { className: 'dashicons dashicons-translation' }),
                        el('strong', null, __('LocalizePilot Language Switcher', 'localizepilot'))
                    ),
                    languages.length < 2 ? el(
                        Notice,
                        { status: 'warning', isDismissible: false },
                        __('Enable at least one additional language in LocalizePilot settings.', 'localizepilot')
                    ) : preview(attributes),
                    el('small', null, __('The live block uses the current page URL and enabled languages.', 'localizepilot'))
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp, window.LocalizePilotSwitcherBlock);
