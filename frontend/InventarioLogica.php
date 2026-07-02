<?php
// Archivo: InventarioLogica.php

function calcularValorInventario($insumos) {
    // Caso extremo: Si no mandan un arreglo válido, devolvemos 0
    if (!is_array($insumos)) return 0.0;
    
    $total = 0;
    foreach ($insumos as $item) {
        $stock = isset($item['stock']) ? (float)$item['stock'] : 0;
        $costo = isset($item['costo']) ? (float)$item['costo'] : 0;
        
        // Camino triste: No puede haber stock ni costo negativo en la vida real
        if ($stock < 0 || $costo < 0) {
            throw new InvalidArgumentException("Datos corruptos: El stock y costo no pueden ser negativos.");
        }
        
        $total += ($stock * $costo);
    }
    
    return $total;
}