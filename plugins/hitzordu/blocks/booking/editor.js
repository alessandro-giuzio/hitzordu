(function (blocks, element, blockEditor, components, i18n) {
  const el = element.createElement;
  const { __, _n, sprintf } = i18n;
  const { InspectorControls, useBlockProps } = blockEditor;
  const { PanelBody, Placeholder, SelectControl, TextControl } = components;

  // Added by PHP: the active meeting types, so the editor can offer a list.
  const types = window.hitzorduMeetingTypes || [];

  blocks.registerBlockType('hitzordu/booking', {
    edit({ attributes, setAttributes }) {
      const blockProps = useBlockProps();
      const current = types.find(type => type.slug === attributes.type);

      const options = types.length
        ? types.map(type => ({ label: type.name, value: type.slug }))
        : [{ label: attributes.type, value: attributes.type }];

      const minutes = current ? Number(current.duration_minutes) : 0;

      const instructions = current
        ? sprintf(
            /* translators: 1: meeting type name, 2: length in minutes. */
            _n(
              'Visitors will book: %1$s (%2$d minute). The calendar appears on the published page.',
              'Visitors will book: %1$s (%2$d minutes). The calendar appears on the published page.',
              minutes,
              'hitzordu',
            ),
            current.name,
            minutes,
          )
        : __('Choose a meeting type in the block settings.', 'hitzordu');

      return el(
        'div',
        blockProps,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: __('Booking settings', 'hitzordu') },
            el(SelectControl, {
              label: __('Meeting type', 'hitzordu'),
              value: attributes.type,
              options,
              onChange: value => setAttributes({ type: value }),
              __next40pxDefaultSize: true,
              __nextHasNoMarginBottom: true,
            }),
            el(TextControl, {
              label: __('Fallback contact URL', 'hitzordu'),
              help: __(
                'Shown to visitors who have JavaScript turned off.',
                'hitzordu',
              ),
              type: 'url',
              value: attributes.contact,
              onChange: value => setAttributes({ contact: value }),
              __next40pxDefaultSize: true,
              __nextHasNoMarginBottom: true,
            }),
          ),
        ),
        el(Placeholder, {
          icon: 'calendar-alt',
          label: __('Booking calendar', 'hitzordu'),
          instructions,
        }),
      );
    },

    // Dynamic block: PHP renders the front end, so nothing is saved in the post.
    save: () => null,
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.blockEditor,
  window.wp.components,
  window.wp.i18n,
);
