(() => {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, RangeControl, TextareaControl } = wp.components;
    const { Fragment, createElement: el } = wp.element;

    registerBlockType('adsm/event-views', {
        edit: (props) => {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Options du bloc' },
                        el(SelectControl, {
                            label: 'Vue par défaut',
                            value: attributes.defaultView,
                            options: [
                                { label: 'Liste', value: 'list' },
                                { label: 'Calendrier', value: 'calendar' }
                            ],
                            onChange: (value) => setAttributes({ defaultView: value })
                        }),
                        el(RangeControl, {
                            label: "Nombre d'événements",
                            value: attributes.numberOfEvents,
                            onChange: (value) => setAttributes({ numberOfEvents: value }),
                            min: 1,
                            max: 10
                        }),
                        el(TextareaControl, {
                            label: 'Code d’embed du calendrier (iframe + style)',
                            help: "Collez le code d’embed généré par The Events Calendar.",
                            value: attributes.embedCode || '',
                            onChange: (value) => setAttributes({ embedCode: value })
                        })
                    )
                ),
                el('div', blockProps,
                    `Vue par défaut: ${attributes.defaultView} — Événements: ${attributes.numberOfEvents} — Embed: ${attributes.embedCode ? '✅' : '❌'}`
                )
            );
        },
        save: () => null
    });
})();