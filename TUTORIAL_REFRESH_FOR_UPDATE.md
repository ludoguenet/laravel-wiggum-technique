# 🎬 refreshForUpdate() — le dernier Golden Ticket

Mini-vidéo (≈ 3 min 30) sur `Model::refreshForUpdate()`, ajouté dans Laravel 13.27 (PR #61247, @stevebauman).
Source : https://laravel-news.com/laravel-refresh-for-update

## Le concept

Il reste **un seul** Golden Ticket pour visiter la chocolaterie Wonka. Charlie le réclame, et Veruca Salt (« I want it NOW! ») l'achète au même moment.

- **Version naïve** : on se fie au modèle chargé au début de la requête. Il est périmé, donc on survend le ticket.
- **Version sûre** : `$tour->refreshForUpdate()` recharge le modèle sur place avec `SELECT … FOR UPDATE`. La donnée est fraîche et la ligne reste verrouillée jusqu'à la fin de la transaction.

## Fichiers de la démo

| Fichier | Rôle |
| --- | --- |
| `app/Models/Tour.php` | Modèle avec `tickets_left` / `tickets_sold` et `hasTicketsLeft()` |
| `database/migrations/2026_09_29_144716_create_tours_table.php` | Table `tours` |
| `database/factories/TourFactory.php` | Factory + état `lastTicket()` |
| `app/Http/Controllers/GoldenTicketController.php` | `claimNaively()`, `claimSafely()`, `reset()` et la simulation `letVerucaClaimFirst()` |
| `routes/api.php` | Routes JSON `tickets.show`, `tickets.reset`, `claim.naively`, `claim.safely` (préfixe `/api`, enregistré dans `bootstrap/app.php`) |
| `tests/Feature/GoldenTicketTest.php` | Tests : survente, refus, succès, reset |

## Commandes (spatie/laravel-visit)

Pas besoin de serveur : `visit` exécute la requête directement dans l'application.

```bash
php artisan visit --route=tickets.show                   # état actuel
php artisan visit --route=tickets.reset --method=post    # remet le dernier ticket
php artisan visit --route=claim.naively --method=post    # ❌ version naïve
php artisan visit --route=claim.safely --method=post     # ✅ refreshForUpdate()
```

Veruca est activée par défaut. Pour la désactiver : `--payload='{"veruca":false}'`.
La version sûre répond en **409** (affiché en rouge par `visit`), c'est normal.

À chaque claim, le contrôleur simule une requête concurrente : il décrémente le stock en base **après** le chargement du modèle.

> ⚠️ SQLite ignore `FOR UPDATE`. La démo montre la relecture d'une donnée fraîche de façon déterministe. Le blocage réel des requêtes concurrentes n'existe que sur MySQL ou PostgreSQL.

## Préparation avant l'enregistrement

1. `php artisan visit --route=tickets.reset --method=post`
2. Ouvrir `GoldenTicketController.php` dans l'éditeur, avec le terminal à côté

---

## Script

### 0:00 – Accroche (15 s)
*[Terminal : `php artisan visit --route=tickets.show` → `tickets_left: 1`]*

> « Il reste **un seul** Golden Ticket pour visiter la chocolaterie de Willy Wonka. Charlie clique. Veruca clique au même moment. Résultat ? Deux tickets vendus… pour un seul ticket. Aujourd'hui, on règle ça en **une ligne**, avec une nouveauté de Laravel 13.27 : `refreshForUpdate()`. »

### 0:15 – Le bug en direct (40 s)
*[Terminal : `php artisan visit --route=claim.naively --method=post`]*

> « Je joue Charlie. Dans la démo, Veruca achète au même moment, comme si c'était une autre requête. J'envoie… »

*[Réponse : « Charlie a son Golden Ticket ! », `tickets_left: -1`, `tickets_sold: 2`]*

> « Charlie a son ticket. Veruca aussi. Et il reste… **moins un** ticket. Wonka n'est pas content. »

### 0:55 – Pourquoi ? (40 s)
*[Code : `claimNaively()`]*

> « Le problème ? On charge `$tour` **au début** de la requête. Entre ce moment-là et notre `if`, Veruca a déjà acheté. Notre modèle en mémoire dit encore "il reste 1 ticket", alors que la base dit 0. On prend une décision sur une donnée **périmée**. La transaction ne suffit pas : elle ne relit pas le modèle toute seule. »

### 1:35 – L'ancienne solution (25 s)
*[Slide ou code]*

```php
$tour = Tour::query()->lockForUpdate()->findOrFail($tour->getKey());
```

> « Avant, il fallait refaire une requête avec `lockForUpdate()` et réassigner la variable. Ça marche, mais c'est verbeux, et on se retrouve avec une **nouvelle instance**. »

### 2:00 – La nouveauté (40 s)
*[Code : `claimSafely()`]*

```php
DB::transaction(function () use ($tour) {
    $tour->refreshForUpdate();

    if (! $tour->hasTicketsLeft()) {
        return false;
    }

    $tour->decrement('tickets_left');
    $tour->increment('tickets_sold');

    return true;
});
```

> « Maintenant, une ligne : `$tour->refreshForUpdate()`. Elle recharge le modèle **sur place** avec un `SELECT … FOR UPDATE`. Elle passe par la connexion d'écriture, donc pas de replica en retard. Et elle recharge aussi les relations déjà chargées. La ligne est verrouillée jusqu'à la fin de la transaction : les autres requêtes qui veulent la verrouiller **attendent leur tour**. »

### 2:40 – Démo corrigée (30 s)
*[Terminal : `php artisan visit --route=tickets.reset --method=post`, puis `php artisan visit --route=claim.safely --method=post`]*

> « On remet le ticket et on recommence. Veruca achète en même temps… "Trop tard, Veruca a eu le dernier ticket." Un seul vendu, zéro restant. Pauvre Charlie, mais la base de données est juste. »

### 3:10 – Les pièges (15 s)

> « Deux règles. Un : **toujours dans une transaction**, sinon le verrou est relâché tout de suite. Deux : sur SQLite, `FOR UPDATE` est ignoré. Le verrou prend tout son sens sur MySQL ou PostgreSQL. »

### 3:25 – Outro (10 s)

> « `refreshForUpdate()` : une ligne, zéro survente, et Wonka garde sa chocolaterie. Abonne-toi, et à la prochaine ! 🍫 »

---

## Prompt vignette

À utiliser avec ta photo en référence :

```
Minimalist modern YouTube thumbnail, 16:9, 1280x720. Photorealistic, clean, premium tech aesthetic.
Not cartoonish, no illustration style.

Use the person from the attached photo. Keep their face and features faithful and recognizable.
Place them on the right side, from the chest up, looking at the camera with a confident,
slightly amused expression, holding a single sleek golden ticket between two fingers.
The ticket is simple: matte gold with a thin engraved border, no text on it.

Background: plain deep dark gradient (near black to dark purple), with a soft warm golden glow
behind the person. Lots of negative space. No other objects, no extra characters, no logos.

Text on the left, large bold modern sans-serif (like Inter or Geist), white, perfectly spelled:
"1 TICKET, 2 ACHETEURS ?"
Below it, smaller, in a monospace font, golden color:
"refreshForUpdate()"

Sharp focus, studio lighting, high contrast, clean composition.
Only the two text lines specified above, nothing else written anywhere in the image.
```

**Autres textes possibles** : « STOP À LA SURVENTE », « 1 LIGNE = 0 BUG ».
