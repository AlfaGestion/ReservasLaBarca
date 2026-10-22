# Carrusel de imágenes de `/app`

Las imágenes predeterminadas son ilustrativas y no representan instalaciones reales de La Barca. Se guardan localmente en `public/assets/player/carousel/` para evitar dependencias externas en producción.

| Archivo | Categoría | Fuente y licencia | Autor |
|---|---|---|---|
| `futbol.jpg` | Fútbol | [Soccer Field (Unsplash), Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Soccer_Field_(Unsplash).jpg) — CC0 | Fuente original de Unsplash, publicada bajo CC0 en Wikimedia Commons |
| `padel.jpg` | Pádel | [Padel court, Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Padel_court.jpg) — CC0 | Samson Ssemakadde |
| `quincho.jpg` | Quincho | [Outdoor Grill, Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Outdoor_Grill.jpg) — CC0 | Barkathnisa Ashaqilahi |
| `eventos.jpg` | Eventos | [Decorated wedding hall in a restaurant, Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Decorated_wedding_hall_in_a_restaurant._(50667953138).jpg) — CC BY 2.0 | Nenad Stojkovic |

## Reemplazar las fotografías

1. Copiar las nuevas imágenes optimizadas en `public/assets/player/carousel/`.
2. Editar únicamente `app/Config/PlayerCarousel.php` para cambiar `image`, `category`, `alt`, `order` o `active`.
3. Mantener las atribuciones y licencias correspondientes si se conserva alguna imagen predeterminada.

El carrusel no presenta estas imágenes como fotografías del complejo.
