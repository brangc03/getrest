Requerimientos Funcionales (Logos):
- Registro de usuario. Cada usuario que deseé utilizar nuestro programa deberá registrarse en el sistema.
- Registro: El usuario debe poder dar de alta recetas que incluyan ingredientes, gramajes, porciones y el procedimiento.
- Costeo: El usuario debe poder visualizar el costo total y por porción de cada receta en el momento de crearla o editarla.
- Roles: El Gerente debe tener permisos para editar todo; el de Almacén para mover inventario y el de Cocina para consultar preparaciones.
- Actualización: El usuario de Almacén debe capturar la cantidad recibida de cada insumo y su fecha de caducidad; esta acción actualiza el inventario y reinicia el cálculo de alertas, conectando su acción directa con la lógica de umbrales del 10% y 30%.

Requerimientos de Experiencia de Usuario (Pathos):
- Alertas: El encargado de almacén debe recibir notificaciones cuando los insumos estén por agotarse.
- Facilidad de navegación: El usuario debe poder acceder a las funciones principales del sistema en un máximo de tres pasos.
- Rendimiento: El sistema debe procesar consultas de inventario y recetas sin afectar el rendimiento general de la plataforma.
- Legibilidad: Las alertas de inventario (críticas y preventivas) deben ser visualmente claras y diferenciables para el personal de Almacén.
- Tiempo de Respuesta: El usuario no debe esperar más de 2 segundos para ver el cálculo automático de costos tras guardar una receta.

Requerimientos de Seguridad/Confianza (Ethos):
- Aislamiento Estricto de Datos por Sucursal: El sistema debe garantizar un aislamiento lógico completo en la base de datos (Multi-tenancy), asegurando que ningún restaurante o usuario externo pueda visualizar, modificar o interferir con los inventarios, costos o recetas de otro establecimiento.
- Respaldo Automático e Integridad de Historiales: El sistema debe ejecutar copias de seguridad automáticas diariamente y mantener un registro de auditoría (logs) inmutable. Esto asegura al administrador que, en caso de cualquier falla técnica o error humano, la información operativa e histórica de sus productos no se perderá y podrá ser recuperada íntegramente.
- Control de Accesos por Roles (RBAC): Para generar confianza en el dueño del negocio, la interfaz debe restringir las acciones según el puesto: el Gerente tendrá permisos totales, el personal de Almacén solo gestionará existencias, y el equipo de Cocina únicamente visualizará procedimientos, impidiendo que empleados no autorizados alteren costos o datos críticos.
- Autenticación Segura y Manejo de Sesiones: El ingreso a la plataforma web de GetRest debe realizarse mediante credenciales individuales encriptadas, implementando políticas de cierre de sesión automático tras periodos de inactividad, evitando que personal ajeno acceda al sistema si una pantalla se queda abierta en el área de cocina.
- Encriptación de Recetas y Fórmulas de Costeo: El sistema debe cifrar mediante algoritmos seguros, toda la información almacenada sobre los ingredientes y procedimientos de las recetas, garantizando que el restaurante esté protegido contra filtraciones externas.

Requerimientos añadidos:
- Base de datos de proveedores, datos ligados a la alarma.
- Historial y gráfica de productos más vendidos y menos vendidos.
