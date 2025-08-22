(() => {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, RangeControl } = wp.components;
    const { createElement: el } = wp.element;

    console.log("✅ Bloc ADSM Event Calendar chargé !");

    registerBlockType("adsm/event-calendar-widget", {
        title: "ADSM Event Calendar Widget",
        icon: "calendar",
        category: "widgets",
        attributes: {
            numberOfEvents: {
                type: "integer",
                default: 5,
            },
        },
        edit: (props) => {
            const { attributes, setAttributes } = props;

            return el("div", {},
                el(InspectorControls, {},
                    el(PanelBody, { title: "Paramètres d’affichage", initialOpen: true },
                        el(RangeControl, {
                            label: "Nombre d’événements à afficher",
                            value: attributes.numberOfEvents,
                            onChange: (value) => setAttributes({ numberOfEvents: value }),
                            min: 1,
                            max: 10,
                        })
                    )
                ),
                el("p", {}, `Affiche ${attributes.numberOfEvents} événements à venir`)
            );
        },
        save: () => null, // rendu côté serveur via PHP
    });
})();