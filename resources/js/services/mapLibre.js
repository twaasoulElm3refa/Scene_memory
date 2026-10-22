import * as maplibregl from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import 'maplibre-gl/dist/maplibre-gl.css';

// Bundle the patched library and its worker together, including the mobile build.
maplibregl.setWorkerUrl(workerUrl);

export default maplibregl;
