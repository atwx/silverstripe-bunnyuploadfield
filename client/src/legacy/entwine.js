import jQuery from 'jquery';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BunnyVideoUploadField } from '../components/BunnyVideoUploadField/BunnyVideoUploadField';

/**
 * Progressively enhances the plain text input rendered by BunnyVideoUploadField::Field()
 * with the React upload UI. This covers form contexts that aren't driven by the React
 * FormBuilder/schema system - e.g. elements with `inline_editable = false`, which fall back
 * to the classic GridFieldDetailForm - where `schemaComponent`/`schemaType: Custom` are not
 * interpreted and the field would otherwise render as a bare text input.
 */
jQuery.entwine('ss', ($) => {
  $('input.bunny-video-upload').entwine({
    ReactRoot: null,

    onmatch() {
      const $input = this;
      const mount = document.createElement('div');
      mount.className = 'bunny-video-upload-field__mount';
      $input.after(mount);
      $input.hide();

      const root = createRoot(mount);
      this.setReactRoot(root);

      const data = {
        endpoint: $input.data('endpoint'),
        libraryId: $input.data('library-id'),
      };

      const renderField = () => {
        root.render(
          <BunnyVideoUploadField
            id={$input.attr('id')}
            name={$input.attr('name')}
            value={$input.val()}
            data={data}
            disabled={$input.prop('disabled')}
            readOnly={$input.prop('readonly')}
            onChange={(newValue) => {
              $input.val(newValue).trigger('change');
              renderField();
            }}
          />
        );
      };

      renderField();
    },

    onunmatch() {
      const root = this.getReactRoot();
      if (root) {
        root.unmount();
        this.setReactRoot(null);
      }
    },
  });
});
