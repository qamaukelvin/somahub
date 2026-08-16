<?php
/**
 * Reusable location picker. Include this inside a <form>.
 * Expects these variables set by the includer before including this file:
 *   $locLat, $locLng   - current values (float or null/empty)
 *   $locFieldPrefix    - prefix for the hidden input names, e.g. "" -> lat/lng,
 *                        or "loc_" -> loc_lat/loc_lng (avoids name collisions
 *                        if a form has more than one picker — usually not needed)
 * Uses OpenStreetMap tiles via Leaflet — no API key required, matches the
 * Leaflet-based tooling already used elsewhere on the platform.
 */
$locFieldPrefix = $locFieldPrefix ?? '';
$pickerId = 'locpicker_' . substr(md5($locFieldPrefix . microtime()), 0, 6);
$defaultLat = $locLat ?: -1.286389;  // Nairobi, as a sane default center
$defaultLng = $locLng ?: 36.817223;
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
  .loc-picker-map{height:260px;border-radius:10px;margin:8px 0;border:1px solid #ccc;}
  .loc-picker-hint{font-size:0.78rem;color:#888;margin-bottom:6px;}
  .loc-picker-coords{font-size:0.8rem;color:#0F5257;font-weight:700;margin-top:6px;}
</style>

<div class="loc-picker-hint">Tap or click on the map to set your school's exact location. Drag the pin to fine-tune it.</div>
<div id="<?= $pickerId ?>" class="loc-picker-map"></div>
<div class="loc-picker-coords" id="<?= $pickerId ?>_coords">
  <?= ($locLat && $locLng) ? "Selected: {$locLat}, {$locLng}" : 'No location set yet' ?>
</div>
<input type="hidden" name="<?= $locFieldPrefix ?>lat" id="<?= $pickerId ?>_lat" value="<?= htmlspecialchars($locLat ?? '') ?>">
<input type="hidden" name="<?= $locFieldPrefix ?>lng" id="<?= $pickerId ?>_lng" value="<?= htmlspecialchars($locLng ?? '') ?>">

<script>
(function() {
  const map = L.map('<?= $pickerId ?>').setView([<?= $defaultLat ?>, <?= $defaultLng ?>], <?= ($locLat && $locLng) ? 15 : 6 ?>);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
  }).addTo(map);

  let marker = null;
  <?php if ($locLat && $locLng): ?>
  marker = L.marker([<?= $locLat ?>, <?= $locLng ?>], {draggable: true}).addTo(map);
  marker.on('dragend', updateFromMarker);
  <?php endif; ?>

  function updateFromMarker() {
    const pos = marker.getLatLng();
    setCoords(pos.lat, pos.lng);
  }

  function setCoords(lat, lng) {
    lat = lat.toFixed(6);
    lng = lng.toFixed(6);
    document.getElementById('<?= $pickerId ?>_lat').value = lat;
    document.getElementById('<?= $pickerId ?>_lng').value = lng;
    document.getElementById('<?= $pickerId ?>_coords').textContent = 'Selected: ' + lat + ', ' + lng;
  }

  map.on('click', function(e) {
    if (marker) {
      marker.setLatLng(e.latlng);
    } else {
      marker = L.marker(e.latlng, {draggable: true}).addTo(map);
      marker.on('dragend', updateFromMarker);
    }
    setCoords(e.latlng.lat, e.latlng.lng);
  });

  // Try to center on the user's actual location if nothing is set yet —
  // makes first-time picking much faster for schools on mobile.
  <?php if (!$locLat || !$locLng): ?>
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      map.setView([pos.coords.latitude, pos.coords.longitude], 14);
    }, function() { /* silently ignore if denied */ });
  }
  <?php endif; ?>
})();
</script>
