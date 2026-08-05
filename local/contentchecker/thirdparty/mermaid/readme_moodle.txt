Mermaid — diagram rendering for local_contentchecker
=====================================================

Vendored deliberately rather than loaded from a CDN. The plugin's premise is
that course content never leaves the environment, and a CDN request leaks which
page a learner is reading to a third party on every view.

Source : https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js
Licence: MIT
Why v10: the v10 build is UMD and sets window.mermaid, which is what
         amd/src/enrichment.js attaches to. The v11 build is ESM only and
         defines no global, so it silently does nothing when loaded this way.

To upgrade, replace the file with another UMD build and re-check that
window.mermaid still exists after loading it.
