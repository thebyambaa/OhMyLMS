/**
 * OhMyLMS Gutenberg Blocks Main File
 *
 * This file loads all individual block scripts
 *
 * @package OhMyLMS
 */

(function () {
  'use strict';

  // The block editor canvas (WP 5.9+) renders inside an <iframe>, so:
  // 1) the frontend body classes OhMyLMS's CSS is scoped under
  //    (ohmylms-page, ohmylms-checkout, etc. - added via the
  //    `body_class` filter, which only ever runs on real page loads) never
  //    reach it, and
  // 2) `wp_enqueue_style('ohmylms-main-editor', .../style.css, ...)`
  //    (BlocksManager::enqueue_block_editor_assets()) only prints into the
  //    top-level wp-admin <head> - WordPress does NOT mirror an ad-hoc
  //    enqueue_block_editor_assets stylesheet into the iframe the way it
  //    does for a block's own declared `style`/`editorStyle` asset.
  // Confirmed live: neither the class nor the <link> ever reach the canvas,
  // so the entire design system (style.css is scoped under `.ohmylms-page`)
  // fails to match, leaving every block looking broken only in the editor.
  function getEditorDocument() {
    var iframe = document.querySelector('iframe[name="editor-canvas"]');
    return iframe && iframe.contentDocument ? iframe.contentDocument : document;
  }
  function syncEditorStylesheet(editorDoc) {
    if (editorDoc === document || editorDoc.getElementById('ohmylms-main-editor-css')) {
      return;
    }
    var sourceLink = document.getElementById('ohmylms-main-editor-css');
    if (!sourceLink) {
      return;
    }
    editorDoc.head.appendChild(sourceLink.cloneNode(true));
  }
  function syncEditorAssets() {
    var editorDoc = getEditorDocument();
    var body = editorDoc && editorDoc.body;
    if (!body || !editorDoc.querySelector('[class*="wp-block-ohmylms-"]')) {
      return;
    }
    syncEditorStylesheet(editorDoc);
    body.classList.add('ohmylms-page');
    if (editorDoc.querySelector('.wp-block-ohmylms-checkout')) {
      body.classList.add('ohmylms-checkout');
    }
  }

  // Initialize blocks when DOM is ready
  wp.domReady(function () {
    console.info('OhMyLMS Blocks Loaded');
    syncEditorAssets();
    if (window.wp && wp.data && wp.data.subscribe) {
      var timeoutId;
      wp.data.subscribe(function () {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(syncEditorAssets, 300);
      });
    }

    // The canvas iframe mounts asynchronously, so poll briefly to catch
    // it (and any ServerSideRender blocks) before the store fires an update.
    var pollId = setInterval(syncEditorAssets, 1000);
    setTimeout(function () {
      clearInterval(pollId);
    }, 20000);
  });
})();
