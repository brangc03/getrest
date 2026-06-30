## 1. Módulo de Seguridad y Navegación
| Id de Prueba | Nombre de la integración | Módulo de Origen | Módulo destino | Resultado esperado | Estado Actual |
| SEC-01 | Validación de Sesión | URL directa (cualquiera) | Pantalla de Login | Impide entrar y redirige al login si no hay sesión. | PASA |
| SEC-02 | Gestión de Roles | Dashboard (Inicio) | Barra superior | Muestra las iniciales y el rol correcto del usuario. | PASA |
| SEC-03 | Navegación Dinámica (UI) | Barra lateral (Menú) | Barra lateral (Menú) | El menú se encoge o expande al darle clic al logo. | PASA |
| SEC-04 | Cierre de sesión (Extra) | Botón "Cerrar sesión" | Pantalla de Login | Cierra la cuenta de forma segura y regresa al login. | PASA |

## 2. Módulo de Recetas (Gestión de Menú)
| Id de Prueba | Nombre de la integración | Módulo de Origen | Módulo destino | Resultado esperado | Estado Actual |
| REC-01 | Listado Inteligente | Menú principal | Pantalla de Recetas | Muestra la tabla de platillos con su estado actual. | PASA |
| REC-02 | Filtros en Tiempo Real | Pantalla de Recetas | Pantalla de Recetas | Encuentra y filtra recetas al escribir o tocar categorías. | PASA |
| REC-03 | Cálculo Financiero al Vuelo | Clic en receta | Panel de detalles | Calcula y muestra el % de ganancia basado en el precio y costo. | PASA |
| REC-04 | Detalle de Receta (Panel de lectura) | Clic en receta | Panel lateral (Detalle) | Despliega los insumos, pasos y especificaciones del platillo. | PASA |
| REC-05 | Creación/Edición Dinámica | Pantalla de Recetas | Panel lateral (Formulario) | Guarda platillos nuevos o actualiza los cambios hechos. | PASA |
| REC-06 | Gestión Relacional (DB) | Panel lateral (Formulario) | Base de datos | Guarda correctamente qué ingredientes lleva cada receta. | PASA |
| REC-07 | Eliminación | Botón borrar (Detalle) | Pantalla de Recetas | Borra la receta del sistema de forma permanente al confirmar. | PASA |

## 3. Módulo de Inventario (Almacén)
| Id de Prueba | Nombre de la integración | Módulo de Origen | Módulo destino | Resultado esperado | Estado Actual |
| INV-01 | Dashboard Rápido (KPIs) | Pantalla de Inventario | Tarjetas superiores | Calcula y muestra el total de insumos, alertas y valor del almacén. | PASA |
| INV-02 | Listado y Búsqueda | Barra de búsqueda | Tabla de insumos | Filtra los ingredientes mostrados según el texto escrito. | PASA |
| INV-03 | Sistema de Alertas | Tabla de insumos | Etiqueta de estado | Marca en rojo "Bajo Stock" si la cantidad es menor al mínimo. | PASA |
| INV-04 | Gestión de Insumos (CRUD) | Panel lateral (Formulario) | Base de datos | Crea, edita o elimina ingredientes correctamente en el sistema. | PASA |

## 4. Módulo de Ventas (Punto de Venta / POS)
| Id de Prueba | Nombre de la integración | Módulo de Origen | Módulo destino | Resultado esperado | Estado Actual |
| VENT-01 | Catálogo Interactivo | Pantalla de Ventas | Tarjetas de menú | Muestra los platillos disponibles organizados por categorías. | PASA |
| VENT-02 | Comanda Virtual (Carrito) | Tarjetas de menú | Panel de Ticket | Suma, resta y calcula el total de productos en tiempo real. | PASA |
| VENT-03 | Procesamiento de Pagos | Panel de Ticket | Formulario de pago | Permite elegir cómo va a pagar el cliente (efectivo o tarjeta). | PASA |
| VENT-04 | Registro de Venta (DB) | Botón Cobrar Ticket | Base de datos | Guarda el dinero y el texto con el detalle de lo vendido. | PASA |
| VENT-05 | Corte de Caja (Historial) | Botón Ventas de Hoy | Panel lateral (Historial) | Muestra la suma total del día y la lista de cuentas cobradas. | PASA |

## 5. Módulo de Reportes (Analíticas)
| Id de Prueba | Nombre de la integración | Módulo de Origen | Módulo destino | Resultado esperado | Estado Actual |
| REP-01 | Métricas Gerenciales (KPIs) | Pantalla de Reportes | Tarjetas superiores | Muestra el valor de todo el almacén, márgenes y proyecciones. | PASA |
| REP-02 | Ranking de Rentabilidad | Base de datos | Tabla de rentabilidad | Acomoda los platillos del que deja más dinero al que deja menos. | PASA |
| REP-03 | Visualización de Datos (Gráficas) | Base de datos | Gráficas animadas | Dibuja de forma visual las tendencias de dinero y categorías. | PASA |
| REP-04 | Listas del Prototipo | Pantalla de Reportes | Bloques del prototipo | Muestra los productos más vendidos, más usados y mermas. | PASA |
| REP-05 | Bitácora de Sistema | Base de datos | Tabla de historial | Despliega la lista de todo lo que han movido los usuarios. | PASA |
