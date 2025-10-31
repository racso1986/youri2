<?php
// $streamingId, $correo, $precioNuevo (soles) ya definidos
// ¿ya había ancla? = ¿existía hijo antes de este insert?
$hadAnchor = getFirstChildPrice($pdo, (int)$streamingId, $correo); // usa la misma función de arriba
$anchorToSend = $hadAnchor !== null ? $hadAnchor : (float)$precioNuevo;

echo json_encode([
  'ok' => true,
  'anchor_price' => number_format($anchorToSend, 2, '.', ''),
  'correo' => $correo,
  'streaming_id' => (int)$streamingId
]);
exit;
