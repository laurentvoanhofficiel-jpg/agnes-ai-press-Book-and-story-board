<?php
/**
 * CINEMATIC STORYBOARD PRO v18 — index.php
 * ------------------------------------------------------------
 * - Analyse d'image COMPLÈTE (7 appels LLM : 3 char + 3 scene + 1 style)
 * - img seul / txt seul / img + txt (complémentaire, pas d'override)
 * - Queue LIFO : série complète ou storyboard prioritaire sur l'ancien pending
 * - Bouton "Vider la liste d'attente" (queue clear)
 * - Toutes les images éditables (gallerie + espace création) : img→txt→img
 * - Wrapper complet : style.css + javascript.js avec cache-busting
 * ------------------------------------------------------------
 */

// ============================================================
// CONFIG
// ============================================================
$API_BASE  = 'https://apihub.agnes-ai.com/v1';
$MODEL_TXT = 'agnes-3.0-flash';
$MODEL_IMG = 'agnes-image-2.5-flash';

$PUBLIC_IMAGE_BASE = 'https://laurent.voanh.art/image';

$MAX_QUEUE_ATTEMPTS   = 10;
$QUEUE_MAX_AGE_HOURS  = 48;
$QUEUE_STALE_SECONDS  = 300;
$BATCH_MAX_DURATION   = 55.0;
$BATCH_CURL_TIMEOUT   = 90;
$RETRY_CURL_TIMEOUT   = 90;

// ============================================================
// 90 STYLES
// ============================================================
$MASTER_STYLES = [
    ["id"=>"f01","name"=>"Fidèle — Cinéma large","desc"=>"Reproduction fidèle en plan large cinéma","category"=>"faithful","faithful"=>true,"photo_type"=>"wide cinematic photograph"],
    ["id"=>"f02","name"=>"Fidèle — Cinéma moyen","desc"=>"Reproduction fidèle en plan moyen cinéma","category"=>"faithful","faithful"=>true,"photo_type"=>"medium cinematic photograph"],
    ["id"=>"f03","name"=>"Fidèle — Cinéma serré","desc"=>"Reproduction fidèle en plan serré cinéma","category"=>"faithful","faithful"=>true,"photo_type"=>"close-up cinematic photograph"],
    ["id"=>"f04","name"=>"Fidèle — Portrait extrême","desc"=>"Reproduction fidèle en très gros plan","category"=>"faithful","faithful"=>true,"photo_type"=>"extreme close-up cinematic portrait"],
    ["id"=>"f05","name"=>"Fidèle — Environnemental","desc"=>"Reproduction fidèle en portrait environnemental","category"=>"faithful","faithful"=>true,"photo_type"=>"environmental cinematic photograph"],
    ["id"=>"f06","name"=>"Fidèle — Documentaire","desc"=>"Reproduction fidèle en photo documentaire","category"=>"faithful","faithful"=>true,"photo_type"=>"documentary photograph"],
    ["id"=>"f07","name"=>"Fidèle — Éditorial","desc"=>"Reproduction fidèle en photo éditoriale","category"=>"faithful","faithful"=>true,"photo_type"=>"editorial photograph"],
    ["id"=>"f08","name"=>"Fidèle — Lumière naturelle","desc"=>"Reproduction fidèle en lumière naturelle","category"=>"faithful","faithful"=>true,"photo_type"=>"natural light photograph"],
    ["id"=>"f09","name"=>"Fidèle — Photogramme","desc"=>"Reproduction fidèle en photogramme de film","category"=>"faithful","faithful"=>true,"photo_type"=>"cinematic film still"],
    ["id"=>"f10","name"=>"Fidèle — Beaux-arts","desc"=>"Reproduction fidèle en photographie d'art","category"=>"faithful","faithful"=>true,"photo_type"=>"fine art photograph"],

    ["id"=>"p01","name"=>"Annie Leibovitz","desc"=>"Portrait studio théâtral","category"=>"photographer","faithful"=>false,"prompt_body"=>"Annie Leibovitz photography, theatrical studio lighting, elaborate set design, rich saturated colors, dramatic pose, celebrity magazine cover quality, medium format film, painterly depth"],
    ["id"=>"p02","name"=>"Richard Avedon","desc"=>"Noir et blanc haute couture","category"=>"photographer","faithful"=>false,"prompt_body"=>"Richard Avedon photography, high contrast black and white, plain seamless white background, elegant fashion, sharp focus on face, editorial Vogue style, 4x5 large format"],
    ["id"=>"p03","name"=>"Henri Cartier-Bresson","desc"=>"Moment décisif","category"=>"photographer","faithful"=>false,"prompt_body"=>"Henri Cartier-Bresson decisive moment, black and white 35mm street photography, geometric composition, candid gesture, Leica capture, natural ambient light, 1950s Parisian style"],
    ["id"=>"p04","name"=>"Steve McCurry","desc"=>"Documentaire voyage","category"=>"photographer","faithful"=>false,"prompt_body"=>"Steve McCurry documentary photography, vivid colors, intense eyes, National Geographic style, warm natural light, environmental portrait, Kodachrome film look, profound gaze"],
    ["id"=>"p05","name"=>"Sebastião Salgado","desc"=>"Noir et blanc épique","category"=>"photographer","faithful"=>false,"prompt_body"=>"Sebastião Salgado black and white photography, epic scale, human dignity, dramatic chiaroscuro, Genesis project aesthetic, extremely detailed, powerful composition"],
    ["id"=>"p06","name"=>"Gregory Crewdson","desc"=>"Cinéma suburbain","category"=>"photographer","faithful"=>false,"prompt_body"=>"Gregory Crewdson cinematic photography, elaborate staged suburban scene, eerie atmospheric lighting, fog and mist, large production, Twilight series aesthetic, unsettling beauty"],
    ["id"=>"p07","name"=>"Peter Lindbergh","desc"=>"Noir et blanc mode brute","category"=>"photographer","faithful"=>false,"prompt_body"=>"Peter Lindbergh black and white fashion photography, natural beauty without retouching, intimate portrait, grain, soft natural light, Pirelli calendar aesthetic, raw authenticity"],
    ["id"=>"p08","name"=>"Irving Penn","desc"=>"Portrait minimaliste","category"=>"photographer","faithful"=>false,"prompt_body"=>"Irving Penn studio photography, minimalist seamless background, precise composition, sharp detail, controlled lighting, Vogue editorial, subjects isolated with sculptural quality"],
    ["id"=>"p09","name"=>"Robert Doisneau","desc"=>"Paris nostalgique","category"=>"photographer","faithful"=>false,"prompt_body"=>"Robert Doisneau black and white photography, nostalgic Parisian street life, romantic candor, post-war Paris, Leica reportage, warm humanist style, moments of poetry"],
    ["id"=>"p10","name"=>"Helmut Newton","desc"=>"Noir et blanc provocant","category"=>"photographer","faithful"=>false,"prompt_body"=>"Helmut Newton black and white fashion photography, bold provocative poses, stark contrast, luxurious settings, powerful subjects, editorial Vogue style, dramatic lighting"],

    ["id"=>"d01","name"=>"Wes Anderson","desc"=>"Symétrie parfaite, pastel","category"=>"director","faithful"=>false,"prompt_body"=>"Wes Anderson cinematic style, perfect symmetry, centered composition, pastel color palette, deadpan whimsy, vintage set design, flat frontal lighting, Grand Budapest Hotel aesthetic, 1.85:1 framing"],
    ["id"=>"d02","name"=>"Stanley Kubrick","desc"=>"Perspective centrale","category"=>"director","faithful"=>false,"prompt_body"=>"Stanley Kubrick cinematic style, one-point perspective, wide angle lens, symmetrical corridors, unsettling precision, sterile environments, clinical framing, stark contrast, 2001 and Shining aesthetic"],
    ["id"=>"d03","name"=>"David Lynch","desc"=>"Surréel onirique","category"=>"director","faithful"=>false,"prompt_body"=>"David Lynch cinematic style, surreal dreamlike atmosphere, unsettling mood, red velvet curtains, dark mystery, Twin Peaks and Mulholland Drive aesthetic, eerie warm light, surreal symbolism"],
    ["id"=>"d04","name"=>"Tim Burton","desc"=>"Gothique whimsique","category"=>"director","faithful"=>false,"prompt_body"=>"Tim Burton cinematic style, gothic whimsy, dark surreal world, pale characters, spiral motifs, black and white stripes, Tim Burton animation aesthetic, melancholy beauty, twisted fairy tale"],
    ["id"=>"d05","name"=>"Wong Kar-wai","desc"=>"Néon Hong Kong","category"=>"director","faithful"=>false,"prompt_body"=>"Wong Kar-wai cinematic style, moody Hong Kong neon, saturated colors, blurred motion, romantic longing, intimate close-ups, in the Mood for Love aesthetic, rain and reflection, temporal dislocation"],
    ["id"=>"d06","name"=>"Akira Kurosawa","desc"=>"Épique samouraï","category"=>"director","faithful"=>false,"prompt_body"=>"Akira Kurosawa cinematic style, dynamic composition, dramatic weather, epic scale, samurai aesthetic, black and white mastery, Seven Samurai framing, movement in depth, humanist heroism"],
    ["id"=>"d07","name"=>"Federico Fellini","desc"=>"Surréalisme baroque italien","category"=>"director","faithful"=>false,"prompt_body"=>"Federico Fellini cinematic style, surreal carnival atmosphere, theatrical, dreamlike, Italian decadence, symbolic imagery, La Dolce Vita and 8½ aesthetic, baroque excess, dream logic"],
    ["id"=>"d08","name"=>"Martin Scorsese","desc"=>"Énergie urbaine","category"=>"director","faithful"=>false,"prompt_body"=>"Martin Scorsese cinematic style, gritty urban energy, dynamic camera, saturated colors, kinetic movement, Taxi Driver and Goodfellas aesthetic, voice-over intimacy, neon night streets"],
    ["id"=>"d09","name"=>"Pedro Almodóvar","desc"=>"Couleurs primaires","category"=>"director","faithful"=>false,"prompt_body"=>"Pedro Almodóvar cinematic style, vibrant primary colors, bold graphic design, melodrama, Spanish flair, saturated reds and yellows, All About My Mother aesthetic, theatrical emotion"],
    ["id"=>"d10","name"=>"Christopher Nolan","desc"=>"Épique IMAX","category"=>"director","faithful"=>false,"prompt_body"=>"Christopher Nolan cinematic style, epic scale, muted desaturated palette, practical realism, IMAX framing, Inception and Interstellar aesthetic, architectural geometry, monumental gravity"],

    ["id"=>"m01","name"=>"Osamu Tezuka","desc"=>"Shonen classique","category"=>"manga","faithful"=>false,"prompt_body"=>"Osamu Tezuka manga style, classic shonen, large expressive eyes, dynamic action lines, clean line art, Astro Boy and Kimba aesthetic, Disney-influenced, black and white ink"],
    ["id"=>"m02","name"=>"Akira Toriyama","desc"=>"Dragon Ball","category"=>"manga","faithful"=>false,"prompt_body"=>"Akira Toriyama manga style, Dragon Ball aesthetic, dynamic action poses, speed lines, muscular characters, clean ink lines, iconic spiky hair, martial arts energy, manga panel"],
    ["id"=>"m03","name"=>"Takehiko Inoue","desc"=>"Slam Dunk, encre","category"=>"manga","faithful"=>false,"prompt_body"=>"Takehiko Inoue manga style, Slam Dunk and Vagabond aesthetic, realistic anatomy, dramatic ink shading, brush-like strokes, cinematic framing, sweat and intensity, sports manga"],
    ["id"=>"m04","name"=>"Rumiko Takahashi","desc"=>"Shonen romantique","category"=>"manga","faithful"=>false,"prompt_body"=>"Rumiko Takahashi manga style, Inuyasha and Ranma ½ aesthetic, expressive faces, comedic timing, clean line art, shojo sensibility, dynamic action with romance, expressive eyes"],
    ["id"=>"m05","name"=>"Naoki Urasawa","desc"=>"Thriller manga","category"=>"manga","faithful"=>false,"prompt_body"=>"Naoki Urasawa manga style, Monster and 20th Century Boys aesthetic, realistic faces, cinematic framing, subtle expressions, psychological thriller tone, precise line work, intense close-ups"],
    ["id"=>"m06","name"=>"Kentaro Miura","desc"=>"Berserk dark fantasy","category"=>"manga","faithful"=>false,"prompt_body"=>"Kentaro Miura manga style, Berserk aesthetic, intricate detailed linework, extreme darkness, brutal violence, gothic dark fantasy, painstaking cross-hatching, epic armor and monsters"],
    ["id"=>"m07","name"=>"Junji Ito","desc"=>"Horreur manga","category"=>"manga","faithful"=>false,"prompt_body"=>"Junji Ito manga style, horror manga, unsettling detailed faces, spiral motifs, body horror, Uzumaki and Tomie aesthetic, creeping dread, obsessive detail, uncanny beauty"],
    ["id"=>"m08","name"=>"Hayao Miyazaki manga","desc"=>"Nausicaä","category"=>"manga","faithful"=>false,"prompt_body"=>"Hayao Miyazaki manga style, Nausicaä of the Valley of the Wind aesthetic, detailed natural environments, expressive characters, watercolor backgrounds, soft pencil shading, environmental wonder"],
    ["id"=>"m09","name"=>"Taiyo Matsumoto","desc"=>"Manga alternatif","category"=>"manga","faithful"=>false,"prompt_body"=>"Taiyo Matsumoto manga style, Ping Pong and Tekkonkinkreet aesthetic, expressive rough lines, urban youth, dynamic street scenes, loose energetic inking, indie manga feel, motion and emotion"],
    ["id"=>"m10","name"=>"CLAMP","desc"=>"Shojo élégant","category"=>"manga","faithful"=>false,"prompt_body"=>"CLAMP manga style, Cardcaptor Sakura and Chobits aesthetic, elegant flowing hair, expressive eyes, delicate line art, shojo magic, ornate details, romantic atmosphere"],

    ["id"=>"pa01","name"=>"Léonard de Vinci","desc"=>"Renaissance, sfumato","category"=>"painting","faithful"=>false,"prompt_body"=>"Leonardo da Vinci painting style, Renaissance oil painting, sfumato technique, soft atmospheric perspective, detailed anatomy, warm earth tones, Mona Lisa and Last Supper aesthetic, museum masterpiece"],
    ["id"=>"pa02","name"=>"Rembrandt","desc"=>"Chiaroscuro doré","category"=>"painting","faithful"=>false,"prompt_body"=>"Rembrandt oil painting style, dramatic chiaroscuro, warm golden light from single source, deep shadows, baroque portrait, Dutch Golden Age, rich impasto texture, museum masterpiece"],
    ["id"=>"pa03","name"=>"Caravaggio","desc"=>"Ténébrisme","category"=>"painting","faithful"=>false,"prompt_body"=>"Caravaggio oil painting style, extreme tenebrism, dramatic single spotlight, deep black background, baroque drama, visceral realism, religious intensity, museum masterpiece"],
    ["id"=>"pa04","name"=>"Claude Monet","desc"=>"Impressionnisme","category"=>"painting","faithful"=>false,"prompt_body"=>"Claude Monet impressionist oil painting, visible brushstrokes, dappled light, pastel palette, plein air atmosphere, water lily pond aesthetic, fleeting light, impressionism masterpiece"],
    ["id"=>"pa05","name"=>"Vincent van Gogh","desc"=>"Post-impressionnisme","category"=>"painting","faithful"=>false,"prompt_body"=>"Vincent van Gogh post-impressionist oil painting, swirling thick impasto, vibrant yellows and blues, expressive brushwork, Starry Night energy, cypress trees, museum masterpiece"],
    ["id"=>"pa06","name"=>"Pablo Picasso","desc"=>"Cubisme","category"=>"painting","faithful"=>false,"prompt_body"=>"Pablo Picasso cubist painting, fragmented geometric faces, multiple viewpoints simultaneously, cool palette, analytical cubism, angular composition, Guernica aesthetic, museum masterpiece"],
    ["id"=>"pa07","name"=>"Salvador Dalí","desc"=>"Surréalisme hyperréaliste","category"=>"painting","faithful"=>false,"prompt_body"=>"Salvador Dalí surrealist oil painting, dreamlike melting clocks, hyperrealistic details, paranoid-critical method, desert landscapes, elephants on stilts, museum masterpiece"],
    ["id"=>"pa08","name"=>"René Magritte","desc"=>"Surréalisme philosophique","category"=>"painting","faithful"=>false,"prompt_body"=>"René Magritte surrealist painting, bowler hats, floating objects, philosophical mystery, pipe and apple motifs, clean flat execution, Belgian surrealism, museum masterpiece"],
    ["id"=>"pa09","name"=>"Henri Matisse","desc"=>"Fauvisme","category"=>"painting","faithful"=>false,"prompt_body"=>"Henri Matisse fauvist painting, bold flat colors, organic patterns, decorative composition, cut-outs style, joyful palette, dance and jazz motifs, museum masterpiece"],
    ["id"=>"pa10","name"=>"Francis Bacon","desc"=>"Expressionnisme torturé","category"=>"painting","faithful"=>false,"prompt_body"=>"Francis Bacon expressionist painting, distorted faces, smeared paint, existential angst, screaming pope series, cage frames, visceral raw emotion, museum masterpiece"],

    ["id"=>"a01","name"=>"Egon Schiele (sans nudité)","desc"=>"Expressionnisme autrichien","category"=>"artist","faithful"=>false,"prompt_body"=>"Egon Schiele style, expressionist portrait, distorted elongated forms, raw nervous lines, muted earth tones, Viennese Secession, fully clothed, sitter with intense gaze, no nudity"],
    ["id"=>"a02","name"=>"Moebius","desc"=>"Ligne claire, déserts SF","category"=>"artist","faithful"=>false,"prompt_body"=>"Moebius (Jean Giraud) style, ligne claire clean line art, vast desert landscapes, science fiction, pastel colors, Arzach and Blueberry aesthetic, French bande dessinée, meditative sci-fi"],
    ["id"=>"a03","name"=>"Enki Bilal","desc"=>"SF dystopique","category"=>"artist","faithful"=>false,"prompt_body"=>"Enki Bilal style, dystopian science fiction, cold palette, geometric cityscapes, surreal biomechanical machines, Nikopol trilogy aesthetic, French comic art, unsettling futuristic visions"],
    ["id"=>"a04","name"=>"Alphonse Mucha","desc"=>"Art nouveau, dorures","category"=>"artist","faithful"=>false,"prompt_body"=>"Alphonse Mucha style, Art Nouveau, ornate floral borders, flowing hair, gold accents, pastel background, decorative poster, sinuous lines, Paris 1900, intricate ornamentation"],
    ["id"=>"a05","name"=>"H.R. Giger","desc"=>"Biomécanique","category"=>"artist","faithful"=>false,"prompt_body"=>"H.R. Giger style, biomechanical, dark chrome, Alien xenomorph aesthetic, skeletal machine fusion, black and gray airbrush, ribbed tubes, nightmarish organic architecture"],
    ["id"=>"a06","name"=>"Akira (Katsuhiro Otomo)","desc"=>"Neo-Tokyo cyberpunk","category"=>"artist","faithful"=>false,"prompt_body"=>"Katsuhiro Otomo Akira manga style, detailed futuristic Neo-Tokyo, dystopian cyberpunk, dynamic motion, precise linework, dramatic shading, motorcycle and neon aesthetic, epic urban scale"],
    ["id"=>"a07","name"=>"David Hockney","desc"=>"Piscines californiennes","category"=>"artist","faithful"=>false,"prompt_body"=>"David Hockney painting style, vivid swimming pools, saturated colors, California light, flat surfaces, Pop art sensibility, portrait with pool and palm tree, A Bigger Splash aesthetic"],
    ["id"=>"a08","name"=>"Edward Hopper","desc"=>"Solitude américaine","category"=>"artist","faithful"=>false,"prompt_body"=>"Edward Hopper painting style, lonely American scenes, harsh window light, urban isolation, muted colors, diner at night aesthetic, cinematic composition, Americana melancholy"],
    ["id"=>"a09","name"=>"Gustav Klimt","desc"=>"Or, motifs décoratifs","category"=>"artist","faithful"=>false,"prompt_body"=>"Gustav Klimt style, gold leaf, decorative patterns, ornamental portrait, Viennese Secession, elaborate mosaics, fully clothed sitter, glowing gold palette, allegorical symbolism"],
    ["id"=>"a10","name"=>"Tamara de Lempicka","desc"=>"Art déco glamour","category"=>"artist","faithful"=>false,"prompt_body"=>"Tamara de Lempicka style, Art Deco painting, glamorous subject, polished chrome-like surfaces, geometric elegance, luxurious 1920s, sophisticated portrait, rich jewel tones"],

    ["id"=>"n01","name"=>"Cyberpunk","desc"=>"Néon, dystopie","category"=>"digital","faithful"=>false,"prompt_body"=>"cyberpunk digital art, neon-drenched city, holographic advertisements, rain-slicked streets, teal and orange grading, futuristic dystopia, Blade Runner aesthetic, chrome and reflection"],
    ["id"=>"n02","name"=>"Vaporwave","desc"=>"Pastel, glitch 80s","category"=>"digital","faithful"=>false,"prompt_body"=>"vaporwave aesthetic, pastel gradients, Greek statue, glitch artifacts, 80s shopping mall aesthetic, purple and pink palette, palm trees, retro futurism, surreal nostalgia"],
    ["id"=>"n03","name"=>"Synthwave","desc"=>"Grille néon","category"=>"digital","faithful"=>false,"prompt_body"=>"synthwave retro 80s aesthetic, neon grid, sunset gradient pink-purple, chrome reflections, palm tree silhouettes, Miami Vice palette, outrun vibe, retro-futuristic"],
    ["id"=>"n04","name"=>"Solarpunk","desc"=>"Futur optimiste","category"=>"digital","faithful"=>false,"prompt_body"=>"solarpunk digital art, lush greenery, sustainable technology, optimistic future, Art Nouveau influence, solar panels integrated with plants, vibrant greens and warm light, hopeful utopia"],
    ["id"=>"n05","name"=>"Low-poly 3D","desc"=>"Géométrique minimaliste","category"=>"digital","faithful"=>false,"prompt_body"=>"low-poly 3D art, geometric faceted surfaces, flat shading, minimalist composition, pastel colors, isometric-style depth, retro video game aesthetic, clean polygonal beauty"],
    ["id"=>"n06","name"=>"Isometric Pixel Art","desc"=>"Diorama rétro","category"=>"digital","faithful"=>false,"prompt_body"=>"isometric pixel art, 30-degree angle, diorama composition, limited color palette, retro game aesthetic, crisp pixels, detailed miniature scene, eBoy and Habbo style, clean and charming"],
    ["id"=>"n07","name"=>"3D Render Octane","desc"=>"Hyperréaliste","category"=>"digital","faithful"=>false,"prompt_body"=>"Octane 3D render, global illumination, hyperrealistic materials, subsurface scattering, ray-traced reflections, cinematic quality, studio lighting, ultra detailed CGI masterpiece"],
    ["id"=>"n08","name"=>"Fractal Art","desc"=>"Psychédélique","category"=>"digital","faithful"=>false,"prompt_body"=>"fractal digital art, infinite detail, Mandelbrot patterns, psychedelic colors, self-similar structure, cosmic geometry, glitch mandala, mathematical beauty, immersive patterns"],
    ["id"=>"n09","name"=>"Matte Painting","desc"=>"Décor cinéma épique","category"=>"digital","faithful"=>false,"prompt_body"=>"film matte painting, epic scale environments, ultra detailed digital painting, cinematic atmospheric perspective, sci-fi or fantasy landscape, ILM aesthetic, seamless photoreal integration, blockbuster scale"],
    ["id"=>"n10","name"=>"Generative AI Abstract","desc"=>"Art génératif","category"=>"digital","faithful"=>false,"prompt_body"=>"generative AI abstract art, glitch aesthetic, procedural patterns, digital abstraction, code-driven visuals, flowing particles, gradient fields, algorithmic beauty, contemporary digital frontier"],

    ["id"=>"po01","name"=>"Affiche Punk","desc"=>"1977 zine DIY","category"=>"poster","faithful"=>false,"prompt_body"=>"1977 punk zine poster, photocopied xerox texture, torn paper collage, ransom-note lettering, neon green and hot pink, DIY aesthetic, Sex Pistols aesthetic, aggressive composition, safety pins and chaos"],
    ["id"=>"po02","name"=>"Affiche Disco","desc"=>"1970s funky","category"=>"poster","faithful"=>false,"prompt_body"=>"1970s disco poster, glitter and mirror ball, bold funky typography, warm oranges and gold, Studio 54 aesthetic, glamorous, star-burst patterns, roller skate energy, groove and funk"],
    ["id"=>"po03","name"=>"Affiche Film Kung-Fu","desc"=>"Shaw Brothers","category"=>"poster","faithful"=>false,"prompt_body"=>"Shaw Brothers kung-fu movie poster, dramatic martial arts poses, sword action, rich hand-painted colors, brush-stroke typography, 1970s Hong Kong cinema, dynamic composition, heroic and colorful"],
    ["id"=>"po04","name"=>"Affiche Tai Chi","desc"=>"Chinois brumeux","category"=>"poster","faithful"=>false,"prompt_body"=>"Chinese martial arts tai chi poster, misty mountain background, flowing motion, yin-yang balance, traditional brush calligraphy, serene atmosphere, ink wash aesthetic, harmonious composition"],
    ["id"=>"po05","name"=>"Skateboard Deck Art","desc"=>"Culture urbaine","category"=>"poster","faithful"=>false,"prompt_body"=>"skateboard deck art, bold graphic composition, urban street culture, dynamic illustration, spray paint energy, 80s Powell Peralta aesthetic, edgy typography, skateboarding iconography"],
    ["id"=>"po06","name"=>"Graffiti Wildstyle","desc"=>"Lettres complexes","category"=>"poster","faithful"=>false,"prompt_body"=>"wildstyle graffiti art, complex interlocking letters, spray paint texture, urban wall, vibrant colors, New York 1980s subway art, 3D lettering, outlines and shine, street culture"],
    ["id"=>"po07","name"=>"Banksy Stencil","desc"=>"Poing politique","category"=>"poster","faithful"=>false,"prompt_body"=>"Banksy street art stencil, political satire, urban brick wall, black and white with red accents, iconic rat or balloon motif, spray paint texture, subversive humor, London street aesthetic"],
    ["id"=>"po08","name"=>"Propagande soviétique","desc"=>"Constructivisme","category"=>"poster","faithful"=>false,"prompt_body"=>"Soviet constructivist propaganda poster, bold geometric shapes, red black and white palette, heroic worker poses, El Lissitzky and Rodchenko style, dynamic diagonals, revolutionary energy"],
    ["id"=>"po09","name"=>"Pop Art Warhol","desc"=>"Sérigraphie pop","category"=>"poster","faithful"=>false,"prompt_body"=>"Andy Warhol pop art, silkscreen repetition, bright flat colors, iconic consumer imagery, Marilyn Diptych aesthetic, high contrast, Campbell soup style, factory studio vibe, bold graphic"],
    ["id"=>"po10","name"=>"Affiche Voyage Art Déco","desc"=>"1930s glamour","category"=>"poster","faithful"=>false,"prompt_body"=>"1930s Art Deco travel poster, geometric design, glamorous, sleek lines, bold typography, streamlined elegance, French Riviera or ocean liner aesthetic, muted but rich palette, romantic wanderlust"],

    ["id"=>"sf01","name"=>"Blade Runner (1982)","desc"=>"Néo-noir cyberpunk","category"=>"scifi","faithful"=>false,"prompt_body"=>"Blade Runner 1982 aesthetic, cyberpunk noir, neon-soaked Los Angeles, constant rain, teal and orange grading, smoke and haze, Ridley Scott visual masterpiece, cinematic dystopia"],
    ["id"=>"sf02","name"=>"Alien (1979)","desc"=>"Biomécanique Giger","category"=>"scifi","faithful"=>false,"prompt_body"=>"Alien 1979 aesthetic, Ridley Scott space horror, H.R. Giger biomechanical design, dark ship corridors, steam and emergency lights, industrial claustrophobia, terrifying beauty, cinematic"],
    ["id"=>"sf03","name"=>"Star Wars","desc"=>"Space opera","category"=>"scifi","faithful"=>false,"prompt_body"=>"Star Wars space opera, epic galactic scale, desert planets, lightsabers and X-wings, John Williams grandeur, iconic costume design, cinematic space fantasy, timeless saga aesthetic"],
    ["id"=>"sf04","name"=>"Dune","desc"=>"Épique désertique","category"=>"scifi","faithful"=>false,"prompt_body"=>"Dune cinematic aesthetic, vast desert epic, monumental brutalist architecture, spice sandstorms, Denis Villeneuve vision, Hans Zimmer cosmic scale, Fremen and sandworms, epic grandeur"],
    ["id"=>"sf05","name"=>"2001: Space Odyssey","desc"=>"Kubrick, monolithe","category"=>"scifi","faithful"=>false,"prompt_body"=>"2001 A Space Odyssey aesthetic, Stanley Kubrick precision, futuristic white interiors, monolith mystery, sterile space stations, HAL red eye, classical orchestral mood, cinematic monument"],
    ["id"=>"sf06","name"=>"Ghost in the Shell","desc"=>"Anime cyberpunk","category"=>"scifi","faithful"=>false,"prompt_body"=>"Ghost in the Shell anime aesthetic, cyberpunk city, Major Motoko Kusanagi, philosophical sci-fi, rain-slicked Hong Kong-inspired streets, Mamoru Oshii vision, haunting atmosphere, 1995 masterpiece"],
    ["id"=>"sf07","name"=>"Akira (1988 film)","desc"=>"Anime Neo-Tokyo","category"=>"scifi","faithful"=>false,"prompt_body"=>"Akira 1988 anime aesthetic, Katsuhiro Otomo vision, Neo-Tokyo cityscape, Kaneda's motorcycle, psychic explosion, hand-drawn cel animation, dystopian cyberpunk epic, landmark anime"],
    ["id"=>"sf08","name"=>"Interstellar","desc"=>"Trou noir Nolan","category"=>"scifi","faithful"=>false,"prompt_body"=>"Interstellar cinematic aesthetic, Kip Thorne black hole physics, epic space, Christopher Nolan cinematography, IMAX scale, dusty earth and cosmic vastness, Hans Zimmer organ score mood, monumental"],
    ["id"=>"sf09","name"=>"The Matrix","desc"=>"Code vert cyberpunk","category"=>"scifi","faithful"=>false,"prompt_body"=>"The Matrix cinematic aesthetic, green digital code rain, cyberpunk, black trench coats, bullet time slow-motion, dystopian simulation, Wachowskis vision, action and philosophy fused, iconic"],
    ["id"=>"sf10","name"=>"Tron","desc"=>"Grille néon cyan","category"=>"scifi","faithful"=>false,"prompt_body"=>"Tron aesthetic, glowing neon grid, digital world, black background with cyan and orange light trails, light cycles, sci-fi virtual reality, 1982 and Legacy aesthetic, sleek futuristic design"],
];

// ============================================================
// 30 PRESETS STORYBOARD
// ============================================================
$PRESET_STORYBOARDS = [
    ["title"=>"01 — Close-Up Émotion","desc"=>"Gros plan visage","template"=>"extreme close-up of {CHAR} face, subtle {EMOTION} expression, {LIGHTING}, {MOOD}, ultra detailed skin texture, cinematic shallow depth of field"],
    ["title"=>"02 — Establishing Wide","desc"=>"Plan large","template"=>"wide establishing cinematic shot of {CHAR} standing in {SCENE}, {LIGHTING}, {MOOD}, environmental scale, dramatic framing"],
    ["title"=>"03 — Marche Vers Caméra","desc"=>"Marche vers caméra","template"=>"medium tracking shot of {CHAR} walking purposefully toward the camera, {SCENE}, {LIGHTING}, {MOOD}, shallow depth of field, determined stride"],
    ["title"=>"04 — Avec Amour","desc"=>"Deux-shot amoureux","template"=>"intimate two-shot of {CHAR} with a love interest, tender gaze, holding hands, {SCENE}, {LIGHTING}, {MOOD}, romantic cinematic framing"],
    ["title"=>"05 — Avec Meilleur Ami","desc"=>"Rire avec ami","template"=>"candid two-shot of {CHAR} laughing with a close friend, natural chemistry, {SCENE}, {LIGHTING}, {MOOD}, relaxed body language"],
    ["title"=>"06 — Confrontation","desc"=>"Face à face tendu","template"=>"tense confrontation shot of {CHAR} facing an intimidating antagonist, {SCENE}, {LIGHTING}, {MOOD}, dramatic angle, cinematic tension, eye contact"],
    ["title"=>"07 — Fête Entre Amis","desc"=>"Groupe joyeux","template"=>"energetic group shot of {CHAR} celebrating with 4-5 friends, raised glasses, {SCENE}, {LIGHTING}, {MOOD}, candid joy, motion, party atmosphere"],
    ["title"=>"08 — Course Foule","desc"=>"Courir dans la foule","template"=>"dynamic tracking shot of {CHAR} running through a dense crowd, {SCENE}, {LIGHTING}, {MOOD}, motion blur, determined face, urgent energy"],
    ["title"=>"09 — Course Rue Vide","desc"=>"Sprint rue déserte","template"=>"dynamic side tracking shot of {CHAR} sprinting down an empty street, {SCENE}, {LIGHTING}, {MOOD}, motion blur background, athletic form, urgency"],
    ["title"=>"10 — Conduite Nocturne","desc"=>"Au volant la nuit","template"=>"cinematic interior shot of {CHAR} driving at night, hands on wheel, focused eyes, {SCENE}, {LIGHTING}, {MOOD}, road lights streaking, quiet intensity"],
    ["title"=>"11 — Moto Poursuite","desc"=>"Motard en fuite","template"=>"dynamic shot of {CHAR} riding a motorcycle at speed, {SCENE}, {LIGHTING}, {MOOD}, wind in hair, motion blur, cinematic chase energy"],
    ["title"=>"12 — Lecture Seule","desc"=>"Lecture contemplative","template"=>"medium shot of {CHAR} reading alone in quiet contemplation, {SCENE}, {LIGHTING}, {MOOD}, intimate stillness, warm atmosphere"],
    ["title"=>"13 — Cuisine Maison","desc"=>"Cuisine, vapeur","template"=>"medium shot of {CHAR} cooking in a kitchen, steam rising, hands busy with ingredients, {SCENE}, {LIGHTING}, {MOOD}, domestic warmth, focused"],
    ["title"=>"14 — Sous la Pluie","desc"=>"Debout sous la pluie","template"=>"medium shot of {CHAR} standing in heavy rain, soaked hair and clothes, {SCENE}, {LIGHTING}, {MOOD}, reflective puddles, moody, cinematic"],
    ["title"=>"15 — Saut Périlleux","desc"=>"Saut en l'air","template"=>"dynamic low-angle shot of {CHAR} jumping mid-air, suspended action, {SCENE}, {LIGHTING}, {MOOD}, dramatic freeze frame, athletic grace"],
    ["title"=>"16 — Danse","desc"=>"Danse libératrice","template"=>"dynamic medium shot of {CHAR} dancing with abandon, arms outstretched, {SCENE}, {LIGHTING}, {MOOD}, motion blur on limbs, ecstatic, joy"],
    ["title"=>"17 — Action Sportive","desc"=>"Sport intense","template"=>"dynamic action shot of {CHAR} playing a sport at peak action, sweat and effort, {SCENE}, {LIGHTING}, {MOOD}, athletic intensity, motion"],
    ["title"=>"18 — Retrouvailles","desc"=>"Étreinte","template"=>"emotional two-shot of {CHAR} embracing someone after a long separation, {SCENE}, {LIGHTING}, {MOOD}, tears of joy, emotional warmth, intimate"],
    ["title"=>"19 — Adieu Déchirant","desc"=>"Adieu, larmes","template"=>"close-up of {CHAR} crying, saying goodbye, {SCENE}, {LIGHTING}, {MOOD}, tears on cheek, raw emotion, heartbreak, cinematic"],
    ["title"=>"20 — Rires Partagés","desc"=>"Amis qui rient","template"=>"candid group shot of {CHAR} laughing with friends around a table, {SCENE}, {LIGHTING}, {MOOD}, warm camaraderie, glasses raised, genuine joy"],
    ["title"=>"21 — Contemplation","desc"=>"Solitude","template"=>"medium shot of {CHAR} alone in deep contemplation, {SCENE}, {LIGHTING}, {MOOD}, isolated composition, quiet introspection, cinematic"],
    ["title"=>"22 — Escalade","desc"=>"Grimper","template"=>"dynamic shot of {CHAR} climbing with determination, muscles straining, {SCENE}, {LIGHTING}, {MOOD}, physical effort, cinematic heroism"],
    ["title"=>"23 — Natation","desc"=>"Nage","template"=>"cinematic shot of {CHAR} swimming through water, {SCENE}, {LIGHTING}, {MOOD}, water reflections, athletic motion, powerful strokes"],
    ["title"=>"24 — Toit Vertigineux","desc"=>"Sur un toit","template"=>"wide shot of {CHAR} standing on a rooftop overlooking a vast city, {SCENE}, {LIGHTING}, {MOOD}, vertiginous height, epic scale, wind"],
    ["title"=>"25 — Avec Enfant","desc"=>"Tendresse enfant","template"=>"warm two-shot of {CHAR} with a young child, playing or teaching, {SCENE}, {LIGHTING}, {MOOD}, genuine joy, tenderness, protective love"],
    ["title"=>"26 — Avec Aîné","desc"=>"Sagesse mentor","template"=>"tender two-shot of {CHAR} with an elderly mentor figure, {SCENE}, {LIGHTING}, {MOOD}, wisdom being shared, generational bond, cinematic"],
    ["title"=>"27 — Découverte","desc"=>"Révélation","template"=>"dramatic medium shot of {CHAR} discovering something shocking, wide eyes, {SCENE}, {LIGHTING}, {MOOD}, revelation, held breath, cinematic"],
    ["title"=>"28 — Pose Victoire","desc"=>"Bras levés","template"=>"heroic wide shot of {CHAR} in victory pose, arms raised, {SCENE}, {LIGHTING}, {MOOD}, triumphant, epic scale, crowd or landscape behind"],
    ["title"=>"29 — Regard d'Adieu","desc"=>"Se retourner","template"=>"close-up of {CHAR} turning to look back one last time, over the shoulder, {SCENE}, {LIGHTING}, {MOOD}, bittersweet, cinematic farewell, longing"],
    ["title"=>"30 — Horizon Final","desc"=>"Fin épique","template"=>"epic wide shot of {CHAR} facing a vast horizon, back to camera, {SCENE}, {LIGHTING}, {MOOD}, cinematic finale, sense of closure and possibility"],
];

// ============================================================
// HELPERS
// ============================================================
function sse($event, $data) {
    echo "event: $event\ndata: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    @ob_flush(); flush();
}
function ssePing() { echo ": ping\n\n"; @ob_flush(); flush(); }
function cleanJson($raw) {
    if (!$raw) return null;
    return json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)), true);
}
function handleId($ch) { return is_object($ch) ? spl_object_id($ch) : (int)$ch; }
function safeSlug($s) {
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$s);
    if ($safe === '' || $safe === '.' || $safe === '..') return 'unknown';
    return $safe;
}
function jexit($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// BUILDERS
// ============================================================
function buildCharSummary($c) {
    if (empty($c)) return "a person";
    $p = [];
    if (!empty($c['age_range'])) $p[] = $c['age_range'] . " years old";
    if (!empty($c['gender_presentation'])) $p[] = $c['gender_presentation'];
    if (!empty($c['ethnicity_primary'])) {
        $e = $c['ethnicity_primary'];
        if (!empty($c['ethnicity_secondary'])) $e .= " mixed with " . $c['ethnicity_secondary'];
        $p[] = $e;
    }
    if (!empty($c['skin_tone'])) {
        $s = $c['skin_tone'];
        if (!empty($c['skin_undertone'])) $s .= " (" . $c['skin_undertone'] . " undertone)";
        $p[] = $s . " skin";
    }
    $hair = [];
    foreach (['hair_color','hair_texture','hair_style'] as $k) if (!empty($c[$k])) $hair[] = $c[$k];
    if ($hair) $p[] = implode(' ', $hair) . " hair";
    $eyes = [];
    foreach (['eye_color','eye_shape'] as $k) if (!empty($c[$k])) $eyes[] = $c[$k];
    if ($eyes) $p[] = implode(' ', $eyes) . " eyes";
    $face = [];
    foreach (['face_shape','jawline','cheekbones'] as $k) if (!empty($c[$k])) $face[] = $c[$k];
    if ($face) $p[] = implode(', ', $face) . " face";
    if (!empty($c['nose_bridge_height'])) $p[] = $c['nose_bridge_height'] . " nose bridge";
    if (!empty($c['lip_thickness'])) $p[] = $c['lip_thickness'] . " lips";
    if (!empty($c['facial_hair_pattern']) && !in_array($c['facial_hair_pattern'], ['none','clean-shaven'])) $p[] = $c['facial_hair_pattern'];
    if (!empty($c['distinguishing_marks']) && is_array($c['distinguishing_marks'])) $p[] = implode(', ', $c['distinguishing_marks']);
    if (!empty($c['body_type'])) $p[] = $c['body_type'] . " build";
    $clo = [];
    foreach (['clothing_top','clothing_bottom','outerwear'] as $k) if (!empty($c[$k])) $clo[] = $c[$k];
    if ($clo) $p[] = "wearing " . implode(', ', $clo);
    if (!empty($c['accessories']) && is_array($c['accessories'])) $p[] = "with " . implode(', ', $c['accessories']);
    if (!empty($c['overall_vibe'])) $p[] = $c['overall_vibe'];
    return implode(', ', $p) ?: "a person";
}

function buildCharacterIdentityOnly($c) {
    if (empty($c)) return "a person";
    $p = [];
    if (!empty($c['age_range'])) $p[] = $c['age_range'] . " years old";
    if (!empty($c['gender_presentation'])) $p[] = $c['gender_presentation'];
    if (!empty($c['ethnicity_primary'])) {
        $e = $c['ethnicity_primary'];
        if (!empty($c['ethnicity_secondary'])) $e .= " mixed with " . $c['ethnicity_secondary'];
        $p[] = $e;
    }
    if (!empty($c['skin_tone'])) $p[] = $c['skin_tone'] . " skin";
    $hair = [];
    foreach (['hair_color','hair_texture','hair_style'] as $k) if (!empty($c[$k])) $hair[] = $c[$k];
    if ($hair) $p[] = implode(' ', $hair) . " hair";
    $eyes = [];
    foreach (['eye_color','eye_shape'] as $k) if (!empty($c[$k])) $eyes[] = $c[$k];
    if ($eyes) $p[] = implode(' ', $eyes) . " eyes";
    foreach (['face_shape','jawline','cheekbones'] as $k) if (!empty($c[$k])) $p[] = $c[$k];
    if (!empty($c['distinguishing_marks']) && is_array($c['distinguishing_marks'])) $p[] = implode(', ', $c['distinguishing_marks']);
    if (!empty($c['body_type'])) $p[] = $c['body_type'] . " build";
    $clo = [];
    foreach (['clothing_top','clothing_bottom','outerwear','clothing_footwear'] as $k) if (!empty($c[$k])) $clo[] = $c[$k];
    if ($clo) $p[] = "wearing " . implode(', ', $clo);
    if (!empty($c['accessories']) && is_array($c['accessories'])) $p[] = "with " . implode(', ', $c['accessories']);
    return implode(', ', $p) ?: "a person";
}

function buildSceneLighting($s) {
    $p = [];
    foreach (['lighting_type','lighting_source','lighting_quality','color_temperature','key_light_color'] as $k) {
        if (!empty($s[$k])) $p[] = $s[$k];
    }
    return implode(', ', $p) ?: 'natural light';
}
function buildSceneMood($s) {
    $p = [];
    foreach (['emotional_tone','cinematic_genre_hint','color_palette'] as $k) {
        if (!empty($s[$k])) $p[] = $s[$k];
    }
    return implode(', ', $p) ?: 'contemplative';
}
function buildSceneContext($s) {
    $p = [];
    foreach (['location_specific','setting_type','time_of_day'] as $k) {
        if (!empty($s[$k])) $p[] = $s[$k];
    }
    return implode(', ', $p) ?: 'cinematic scene';
}
function buildSceneDecor($s) {
    $p = [];
    if (!empty($s['location_specific'])) $p[] = $s['location_specific'];
    if (!empty($s['setting_type'])) $p[] = $s['setting_type'];
    if (!empty($s['architecture_style'])) $p[] = $s['architecture_style'];
    if (!empty($s['furnishings']) && is_array($s['furnishings'])) $p[] = implode(', ', $s['furnishings']);
    return implode(', ', $p) ?: 'an interior space';
}
function buildSceneFull($s) {
    $p = [];
    foreach (['location_specific','setting_type','location_era','architecture_style','time_of_day','weather','season','atmosphere_texture'] as $k) {
        if (!empty($s[$k])) $p[] = $s[$k];
    }
    if (!empty($s['furnishings']) && is_array($s['furnishings'])) $p[] = implode(', ', $s['furnishings']);
    if (!empty($s['cultural_markers']) && is_array($s['cultural_markers'])) $p[] = implode(', ', $s['cultural_markers']);
    return implode(', ', $p) ?: 'a cinematic scene';
}
function buildSceneComposition($s) {
    $p = [];
    foreach (['composition','camera_angle','focal_length_estimate','depth_of_field','lighting_direction','shadow_character'] as $k) {
        if (!empty($s[$k])) $p[] = $s[$k];
    }
    return implode(', ', $p) ?: 'cinematic composition';
}
function buildSceneLocationOnly($s) {
    $p = [];
    foreach (['location_specific','setting_type','architecture_style','time_of_day','weather','season'] as $k) {
        if (!empty($s[$k])) $p[] = $s[$k];
    }
    if (!empty($s['furnishings']) && is_array($s['furnishings'])) $p[] = implode(', ', $s['furnishings']);
    if (!empty($s['cultural_markers']) && is_array($s['cultural_markers'])) $p[] = implode(', ', $s['cultural_markers']);
    return implode(', ', $p) ?: 'a location';
}
function buildStyleDescription($st) {
    if (empty($st)) return '';
    $p = [];
    foreach (['medium','technique','color_palette','color_grading','lighting_style','texture','line_quality','art_movement','overall_style_description'] as $k) {
        if (!empty($st[$k])) $p[] = $st[$k];
    }
    return implode(', ', $p);
}

/**
 * v18 — Directive utilisateur :
 *  - img + txt : le texte est un COMPLÉMENT ("also include") qui s'ajoute à la scène analysée
 *                → ne remplace jamais les détails de l'analyse
 *  - txt seul  : le texte est la description PRINCIPALE de la scène
 */
function buildUserDirective($textPrompt, $hasImage) {
    if ($textPrompt === null || trim($textPrompt) === '') return '';
    $t = trim($textPrompt);
    if ($hasImage) {
        return " ALSO INCLUDE IN THE SCENE (user's additional element, must be visible but must NOT replace the analyzed subject/scene): \"{$t}\". Integrate it naturally with the analyzed subject and environment. ";
    }
    return " PRIMARY SCENE DESCRIPTION (this is what the image depicts): \"{$t}\". ";
}

// ============================================================
// IP / SESSION / DISK
// ============================================================
function getUserIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return trim($_SERVER['HTTP_X_REAL_IP']);
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
function getImageDir($ip, $sessionId = null) {
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'image';
    if (!is_dir($base)) @mkdir($base, 0755, true);
    $dir = $base . DIRECTORY_SEPARATOR . safeSlug($ip);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if ($sessionId !== null) {
        $dir = $dir . DIRECTORY_SEPARATOR . safeSlug($sessionId);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
    }
    return $dir;
}
function saveSessionInfo($ip, $sessionId, $character, $scene, $style, $mode, $referenceUrl = null, $textPrompt = '', $extra = []) {
    $dir = getImageDir($ip, $sessionId);
    $path = $dir . DIRECTORY_SEPARATOR . 'session_info.json';
    $data = array_merge([
        'timestamp'     => date('c'),
        'mode'          => $mode,
        'character'     => $character,
        'scene'         => $scene,
        'style'         => $style,
        'reference_url' => $referenceUrl,
        'text_prompt'   => $textPrompt,
    ], $extra);
    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function readSessionInfo($ip, $sessionId) {
    if (empty($sessionId)) return null;
    $path = getImageDir($ip, $sessionId) . DIRECTORY_SEPARATOR . 'session_info.json';
    if (!file_exists($path)) return null;
    return json_decode(@file_get_contents($path), true) ?: null;
}
function saveReferenceImage($ip, $sessionId, $tmpFile, $mimeType) {
    global $PUBLIC_IMAGE_BASE;
    $dir = getImageDir($ip, $sessionId);
    $ext = 'png';
    if (strpos($mimeType, 'jpeg') !== false) $ext = 'jpg';
    elseif (strpos($mimeType, 'webp') !== false) $ext = 'webp';
    elseif (strpos($mimeType, 'gif') !== false) $ext = 'gif';
    $filename = 'reference.' . $ext;
    $path = $dir . DIRECTORY_SEPARATOR . $filename;
    if (@copy($tmpFile, $path)) {
        return rtrim($PUBLIC_IMAGE_BASE, '/') . '/' . safeSlug($ip) . '/' . safeSlug($sessionId) . '/' . $filename;
    }
    return null;
}
function saveImageToDisk($ip, $sessionId, $agnesUrl, $prompt, $extraMeta = []) {
    global $PUBLIC_IMAGE_BASE;
    if (empty($agnesUrl)) return null;
    $dir = getImageDir($ip, $sessionId);
    $safeIp = safeSlug($ip);
    $safeSession = safeSlug($sessionId);

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
    $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

    $ch = curl_init($agnesUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TCP_KEEPALIVE  => 1,
        CURLOPT_ENCODING       => '',
    ]);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_errno($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200 || !$imgData) return null;
    if (@file_put_contents($filepath, $imgData) === false) return null;

    $serverUrl = rtrim($PUBLIC_IMAGE_BASE, '/') . '/' . $safeIp . '/' . $safeSession . '/' . $filename;

    $jsonPath = $dir . DIRECTORY_SEPARATOR . 'metadata.json';
    $entry = array_merge([
        'timestamp'  => date('c'),
        'filename'   => $filename,
        'agnes_url'  => $agnesUrl,
        'server_url' => $serverUrl,
        'prompt'     => $prompt,
    ], $extraMeta);

    $fp = @fopen($jsonPath, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $content = stream_get_contents($fp);
        $existing = [];
        if ($content) {
            $parsed = json_decode($content, true);
            if (is_array($parsed)) $existing = $parsed;
        }
        $existing[] = $entry;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        if ($fp) fclose($fp);
    }
    return $serverUrl;
}

// ============================================================
// SQLITE — clés API
// ============================================================
function getDB() {
    static $db = null;
    if ($db !== null) return $db;
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . 'keys.db';
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 10);
    try {
        $db->exec("PRAGMA journal_mode=WAL");
        $db->exec("PRAGMA synchronous=NORMAL");
    } catch (Exception $e) {}
    $db->exec("CREATE TABLE IF NOT EXISTS api_keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_value TEXT UNIQUE NOT NULL,
        rpm INTEGER DEFAULT 10,
        enabled INTEGER DEFAULT 1,
        cooldown_until REAL DEFAULT 0,
        total_requests INTEGER DEFAULT 0,
        total_errors INTEGER DEFAULT 0,
        last_used REAL DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS key_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_id INTEGER NOT NULL,
        used_at REAL NOT NULL,
        success INTEGER DEFAULT 1
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_key_usage_time ON key_usage(key_id, used_at)");
    return $db;
}
function syncAndLoadKeys($rawKeys) {
    $db = getDB();
    $parsed = [];
    foreach ($rawKeys as $k) {
        if (strpos($k, ':') !== false) {
            list($key, $rpm) = explode(':', $k, 2);
            $key = trim($key); $rpm = max(1, min(120, (int)trim($rpm)));
        } else {
            $key = trim($k); $rpm = 10;
        }
        if ($key === '') continue;
        $parsed[$key] = $rpm;
    }
    if (empty($parsed)) return [];
    $stmt = $db->prepare("INSERT INTO api_keys (key_value, rpm, enabled) VALUES (?, ?, 1)
        ON CONFLICT(key_value) DO UPDATE SET rpm = excluded.rpm, enabled = 1");
    foreach ($parsed as $k => $rpm) $stmt->execute([$k, $rpm]);
    $placeholders = implode(',', array_fill(0, count($parsed), '?'));
    $db->prepare("UPDATE api_keys SET enabled = 0 WHERE key_value NOT IN ($placeholders)")
        ->execute(array_keys($parsed));

    $keys = $db->query("SELECT * FROM api_keys WHERE enabled = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $now = microtime(true);
    $state = [];
    $usageStmt = $db->prepare("SELECT used_at FROM key_usage WHERE key_id = ? AND used_at > ? ORDER BY used_at ASC");
    foreach ($keys as $k) {
        $usageStmt->execute([$k['id'], $now - 60]);
        $recent = $usageStmt->fetchAll(PDO::FETCH_COLUMN);
        $history = array_map('floatval', $recent);
        $last = (float)$k['last_used'];
        if (!empty($history) && end($history) > $last) $last = end($history);
        $state[] = [
            'db_id'          => (int)$k['id'],
            'key'            => $k['key_value'],
            'rpm'            => (int)$k['rpm'],
            'last'           => $last,
            'history'        => $history,
            'cooldown_until' => (float)$k['cooldown_until'],
        ];
    }
    return $state;
}
function recordKeyUsage($db_id, $success = true) {
    if ($db_id === null) return;
    try {
        $db = getDB();
        $now = microtime(true);
        $db->prepare("INSERT INTO key_usage (key_id, used_at, success) VALUES (?, ?, ?)")
            ->execute([$db_id, $now, $success ? 1 : 0]);
        if ($success) {
            $db->prepare("UPDATE api_keys SET total_requests = total_requests + 1, last_used = ? WHERE id = ?")
                ->execute([$now, $db_id]);
        } else {
            $db->prepare("UPDATE api_keys SET total_errors = total_errors + 1, last_used = ? WHERE id = ?")
                ->execute([$now, $db_id]);
        }
    } catch (Exception $e) {}
}
function recordKeyCooldown($db_id, $seconds) {
    if ($db_id === null) return;
    try {
        $db = getDB();
        $db->prepare("UPDATE api_keys SET cooldown_until = ? WHERE id = ?")
            ->execute([microtime(true) + $seconds, $db_id]);
    } catch (Exception $e) {}
}

// ============================================================
// QUEUE — LIFO + priorité + clear
// ============================================================
function queuePath($ip) {
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . safeSlug($ip);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . DIRECTORY_SEPARATOR . 'queue.json';
}
function queueReadAll($ip) {
    $path = queuePath($ip);
    if (!file_exists($path)) return [];
    $c = @file_get_contents($path);
    $p = $c ? json_decode($c, true) : [];
    return is_array($p) ? $p : [];
}
function queueClearAll($ip) {
    $path = queuePath($ip);
    if (file_exists($path)) @unlink($path);
}
/** Remplace TOUTE la queue pending (garde processing) → priorité nouvelle série/storyboard */
function queueReplaceAllPending($ip, $newItems) {
    if (empty($newItems)) return;
    $path = queuePath($ip);
    $fp = @fopen($path, 'c+');
    if (!$fp) return;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return; }
    $content = stream_get_contents($fp);
    $items = $content ? (json_decode($content, true) ?: []) : [];
    if (!is_array($items)) $items = [];
    $kept = [];
    foreach ($items as $it) {
        if (($it['status'] ?? '') === 'processing') $kept[] = $it;
    }
    foreach ($newItems as $it) $kept[] = $it;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($kept, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
/** Ajout simple (pour modify) */
function queueAddMany($ip, $newItems) {
    if (empty($newItems)) return;
    global $MAX_QUEUE_ATTEMPTS, $QUEUE_MAX_AGE_HOURS;
    $path = queuePath($ip);
    $fp = @fopen($path, 'c+');
    if (!$fp) return;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return; }
    $content = stream_get_contents($fp);
    $existing = $content ? (json_decode($content, true) ?: []) : [];
    if (!is_array($existing)) $existing = [];
    $cutoff = time() - ($QUEUE_MAX_AGE_HOURS * 3600);
    $existing = array_values(array_filter($existing, function($t) use ($cutoff, $MAX_QUEUE_ATTEMPTS) {
        $created = strtotime($t['created_at'] ?? '2000-01-01') ?: 0;
        $attempts = (int)($t['attempts'] ?? 0);
        return $created > $cutoff && $attempts < $MAX_QUEUE_ATTEMPTS;
    }));
    foreach ($newItems as $t) $existing[] = $t;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
function queueDrain($ip, $limit = 4) {
    global $MAX_QUEUE_ATTEMPTS, $QUEUE_MAX_AGE_HOURS, $QUEUE_STALE_SECONDS;
    $path = queuePath($ip);
    if (!file_exists($path)) return ['items'=>[], 'remaining'=>0, 'locked'=>false];
    $fp = @fopen($path, 'c+');
    if (!$fp) return ['items'=>[], 'remaining'=>0, 'locked'=>true];
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return ['items'=>[], 'remaining'=>0, 'locked'=>true]; }
    $content = stream_get_contents($fp);
    $items = $content ? (json_decode($content, true) ?: []) : [];
    if (!is_array($items)) $items = [];

    $now = time();
    $cutoff = $now - ($QUEUE_MAX_AGE_HOURS * 3600);
    $staleAt = $now - $QUEUE_STALE_SECONDS;

    $purged = [];
    foreach ($items as $it) {
        $created = strtotime($it['created_at'] ?? '2000-01-01') ?: 0;
        $attempts = (int)($it['attempts'] ?? 0);
        if ($created < $cutoff || $attempts >= $MAX_QUEUE_ATTEMPTS) continue;
        $status = $it['status'] ?? 'pending';
        if ($status === 'processing') {
            $since = strtotime($it['processing_since'] ?? '2000-01-01') ?: 0;
            if ($since < $staleAt) {
                $it['status'] = 'pending';
                $it['processing_since'] = null;
            }
        }
        $purged[] = $it;
    }

    $prioOrder = ['urgent'=>0, 'current'=>1, 'user'=>2, 'retry'=>3];
    $pendingIdx = [];
    foreach ($purged as $i => $it) {
        if (($it['status'] ?? 'pending') === 'pending') $pendingIdx[] = $i;
    }
    usort($pendingIdx, function($ia, $ib) use ($purged, $prioOrder) {
        $a = $purged[$ia]; $b = $purged[$ib];
        $pa = $prioOrder[$a['priority'] ?? 'retry'] ?? 3;
        $pb = $prioOrder[$b['priority'] ?? 'retry'] ?? 3;
        if ($pa !== $pb) return $pa - $pb;
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    $selected = array_slice($pendingIdx, 0, $limit);

    $drained = [];
    $nowIso = date('c');
    foreach ($selected as $idx) {
        $purged[$idx]['status'] = 'processing';
        $purged[$idx]['processing_since'] = $nowIso;
        $purged[$idx]['attempts'] = (int)($purged[$idx]['attempts'] ?? 0) + 1;
        $drained[] = $purged[$idx];
    }
    $remaining = 0;
    foreach ($purged as $it) if (($it['status'] ?? 'pending') === 'pending') $remaining++;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(array_values($purged), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return ['items'=>$drained, 'remaining'=>$remaining, 'locked'=>false];
}
function queueMarkResult($ip, $itemId, $success, $errorCode = null) {
    $path = queuePath($ip);
    if (!file_exists($path) || empty($itemId)) return;
    $fp = @fopen($path, 'c+');
    if (!$fp) return;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return; }
    $content = stream_get_contents($fp);
    $items = $content ? (json_decode($content, true) ?: []) : [];
    if (!is_array($items)) $items = [];
    $out = [];
    foreach ($items as $it) {
        if (($it['id'] ?? '') === $itemId) {
            if ($success) continue;
            $it['status'] = 'pending';
            $it['processing_since'] = null;
            $it['last_error'] = $errorCode;
            if (($it['priority'] ?? '') === 'urgent') $it['priority'] = 'retry';
        }
        $out[] = $it;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
function queueMarkUrgent($ip, $itemId) {
    $path = queuePath($ip);
    if (!file_exists($path) || empty($itemId)) return null;
    $fp = @fopen($path, 'c+');
    if (!$fp) return null;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return null; }
    $content = stream_get_contents($fp);
    $items = $content ? (json_decode($content, true) ?: []) : [];
    if (!is_array($items)) $items = [];
    $found = null;
    foreach ($items as &$it) {
        if (($it['id'] ?? '') === $itemId) {
            $it['status'] = 'processing';
            $it['priority'] = 'urgent';
            $it['processing_since'] = date('c');
            $it['attempts'] = (int)($it['attempts'] ?? 0) + 1;
            $found = $it;
            break;
        }
    }
    unset($it);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $found;
}
function queueCountPending($ip) {
    $items = queueReadAll($ip);
    $c = 0;
    foreach ($items as $it) if (($it['status'] ?? 'pending') === 'pending') $c++;
    return $c;
}

// ============================================================
// API — img→txt & txt→img (INCHANGÉ)
// ============================================================
function imgToTxt($imageDataUri, $systemPrompt, $userText, $maxTokens = 700) {
    global $MODEL_TXT;
    if (empty($imageDataUri) || strpos($imageDataUri, 'data:image') !== 0) throw new Exception("imgToTxt: image_url manquante");
    return [
        'model' => $MODEL_TXT, 'temperature' => 0.25, 'max_tokens' => $maxTokens,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $userText],
                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUri]]
            ]]
        ]
    ];
}
function txtToImg($prompt, $ratio, $extraSuffix = ', masterpiece, best quality, no text, no watermark') {
    global $MODEL_IMG;
    if (empty($prompt)) throw new Exception("txtToImg: prompt vide");
    $body = [
        'model' => $MODEL_IMG,
        'prompt' => $prompt . $extraSuffix,
        'size' => '1K',
        'ratio' => $ratio,
        'extra_body' => ['response_format' => 'url']
    ];
    $forbidden = ['image','image_url','init_image','reference_image','img','source_image','input_image'];
    foreach ($forbidden as $f) {
        if (array_key_exists($f, $body)) throw new Exception("txtToImg: champ interdit '$f'");
    }
    return $body;
}

// ============================================================
// KEY STATE
// ============================================================
function chooseKeyIndex(&$keyState, $now) {
    $bestIdx = -1; $bestWait = PHP_INT_MAX;
    foreach ($keyState as $i => $ks) {
        if (($ks['cooldown_until'] ?? 0) > $now) continue;
        $rpm = max(1, (int)($ks['rpm'] ?? 10));
        $history = $ks['history'] ?? [];
        if (count($history) >= $rpm) continue;
        $minIntervalKey = 60.0 / $rpm;
        $w = max(0, $minIntervalKey - ($now - $ks['last']));
        if ($w < $bestWait) { $bestWait = $w; $bestIdx = $i; }
    }
    return [$bestIdx, $bestWait];
}
function purgeKeyHistory(&$keyState, $now) {
    $cutoff = $now - 60.0;
    foreach ($keyState as $i => $ks) {
        $h = $ks['history'] ?? [];
        $h = array_values(array_filter($h, fn($t) => $t > $cutoff));
        $keyState[$i]['history'] = $h;
    }
}

// ============================================================
// EVENT LOOPS
// ============================================================
function runEventLoop(&$keyState, $initialTasks) {
    global $API_BASE;
    if (empty($initialTasks)) return;
    $mh = curl_multi_init();
    @curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, 60);
    @curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 60);
    $pending = $initialTasks;
    $active = [];
    $lastPing = microtime(true);
    $MAX_TOTAL_RETRIES = 5;

    while (!empty($pending) || !empty($active)) {
        if (microtime(true) - $lastPing > 20) { ssePing(); $lastPing = microtime(true); }
        $now = microtime(true);
        purgeKeyHistory($keyState, $now);

        $launchBreak = false;
        while (!empty($pending) && !$launchBreak) {
            $now = microtime(true);
            list($bestIdx, $bestWait) = chooseKeyIndex($keyState, $now);
            if ($bestIdx === -1) {
                if (empty($active)) { usleep(500000); continue; }
                $launchBreak = true; break;
            }
            if ($bestWait > 0.03) {
                if (empty($active)) { usleep((int)(min($bestWait, 1.0) * 1000000)); continue; }
                $launchBreak = true; break;
            }
            $keyState[$bestIdx]['last'] = $now;
            $keyState[$bestIdx]['history'][] = $now;
            $key = $keyState[$bestIdx]['key'];
            $task = array_shift($pending);
            $ch = curl_init($API_BASE . $task['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($task['body']),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 300,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TCP_KEEPALIVE  => 1,
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[handleId($ch)] = ['ch'=>$ch, 'task'=>$task, 'keyIdx'=>$bestIdx];
        }

        do { $status = curl_multi_exec($mh, $running); } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $id = handleId($ch);
            if (!isset($active[$id])) continue;
            $task = $active[$id]['task'];
            $keyIdx = $active[$id]['keyIdx'];
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
            unset($active[$id]);
            $retries = $task['totalRetries'] ?? 0;

            $db_id = $keyState[$keyIdx]['db_id'] ?? null;
            $success = ($code === 200);
            recordKeyUsage($db_id, $success);

            if ($errno && $retries < $MAX_TOTAL_RETRIES) { $task['totalRetries'] = $retries + 1; $pending[] = $task; continue; }
            if ($code === 429) {
                $keyState[$keyIdx]['cooldown_until'] = microtime(true) + 15;
                recordKeyCooldown($db_id, 15);
                if ($retries < $MAX_TOTAL_RETRIES) { $task['totalRetries'] = $retries + 1; $pending[] = $task; continue; }
                if (is_callable($task['onComplete'])) call_user_func($task['onComplete'], $body, 429, $task['meta']);
                continue;
            }
            if (in_array($code, [500,502,503,504]) && $retries < $MAX_TOTAL_RETRIES) { $task['totalRetries'] = $retries + 1; $pending[] = $task; continue; }
            if (is_callable($task['onComplete'])) {
                $newTasks = call_user_func($task['onComplete'], $body, $code, $task['meta']);
                if (is_array($newTasks) && !empty($newTasks)) foreach ($newTasks as $nt) $pending[] = $nt;
            }
        }

        if (!empty($active)) curl_multi_select($mh, 0.2);
    }
    curl_multi_close($mh);
}

function runEventLoopBatch(&$keyState, $tasks, $maxDuration, $curlTimeout) {
    global $API_BASE;
    $results = [];
    if (empty($tasks)) return $results;
    $startTime = microtime(true);
    $mh = curl_multi_init();
    @curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, 60);
    @curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 60);
    $pending = $tasks;
    $active = [];

    while (!empty($pending) || !empty($active)) {
        $now = microtime(true);
        $elapsed = $now - $startTime;
        purgeKeyHistory($keyState, $now);

        if ($elapsed > $maxDuration) {
            foreach ($active as $a) {
                curl_multi_remove_handle($mh, $a['ch']);
                curl_close($a['ch']);
                $results[] = ['task'=>$a['task'], 'code'=>0, 'body'=>'', 'errno'=>28, 'timeout'=>true];
            }
            foreach ($pending as $t) $results[] = ['task'=>$t, 'code'=>0, 'body'=>'', 'errno'=>28, 'timeout'=>true];
            break;
        }

        $launchBreak = false;
        while (!empty($pending) && !$launchBreak) {
            $now = microtime(true);
            list($bestIdx, $bestWait) = chooseKeyIndex($keyState, $now);
            if ($bestIdx === -1) {
                if (empty($active)) {
                    if ($now - $startTime > $maxDuration - 1) { $launchBreak = true; break; }
                    usleep(500000); continue;
                }
                $launchBreak = true; break;
            }
            if ($bestWait > 0.03) {
                if (empty($active)) {
                    if ($now + $bestWait - $startTime > $maxDuration - 2) { $launchBreak = true; break; }
                    usleep((int)(min($bestWait, 1.0) * 1000000));
                    continue;
                }
                $launchBreak = true; break;
            }
            $keyState[$bestIdx]['last'] = $now;
            $keyState[$bestIdx]['history'][] = $now;
            $key = $keyState[$bestIdx]['key'];
            $task = array_shift($pending);
            $ch = curl_init($API_BASE . $task['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($task['body']),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $curlTimeout,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TCP_KEEPALIVE  => 1,
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[handleId($ch)] = ['ch'=>$ch, 'task'=>$task, 'keyIdx'=>$bestIdx];
        }

        if (empty($active) && !empty($pending)) {
            if ($now - $startTime > $maxDuration) {
                foreach ($pending as $t) $results[] = ['task'=>$t, 'code'=>0, 'body'=>'', 'errno'=>28, 'timeout'=>true];
                break;
            }
            usleep(500000);
            continue;
        }

        do { $status = curl_multi_exec($mh, $running); } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $id = handleId($ch);
            if (!isset($active[$id])) continue;
            $task = $active[$id]['task'];
            $keyIdx = $active[$id]['keyIdx'];
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
            unset($active[$id]);

            $db_id = $keyState[$keyIdx]['db_id'] ?? null;
            $success = ($code === 200);
            recordKeyUsage($db_id, $success);

            if ($code === 429) {
                $keyState[$keyIdx]['cooldown_until'] = microtime(true) + 15;
                recordKeyCooldown($db_id, 15);
                $results[] = ['task'=>$task, 'code'=>429, 'body'=>$body, 'errno'=>$errno];
                continue;
            }
            $results[] = ['task'=>$task, 'code'=>$code, 'body'=>$body, 'errno'=>$errno];
        }

        if (!empty($active)) curl_multi_select($mh, 0.1);
    }

    curl_multi_close($mh);
    return $results;
}

// ============================================================
// QUEUE ITEM FACTORY
// ============================================================
function buildTaskFromQueueItem($item) {
    $meta = $item['meta'] ?? [];
    $meta['_item_id'] = $item['id'];
    $meta['_kind'] = $item['kind'] ?? 'style';
    $meta['_session_id'] = $item['session_id'] ?? 'unknown';
    return [
        'endpoint' => $item['endpoint'] ?? '/images/generations',
        'body' => $item['body'],
        'meta' => $meta,
    ];
}
function makeQueueItem($sessionId, $kind, $body, $meta, $priority = 'current') {
    return [
        'id'         => 'q_' . uniqid('', true),
        'session_id' => $sessionId,
        'kind'       => $kind,
        'endpoint'   => '/images/generations',
        'body'       => $body,
        'meta'       => $meta,
        'priority'   => $priority,
        'attempts'   => 0,
        'created_at' => date('c'),
        'status'     => 'pending',
        'processing_since' => null,
        'last_error' => null,
    ];
}

// ============================================================
// ENDPOINT : ?gallery=1
// ============================================================
if (isset($_GET['gallery'])) {
    $ip = getUserIp();
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . safeSlug($ip);

    if (!empty($_GET['session_id'])) {
        $sessId = safeSlug($_GET['session_id']);
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $limit  = max(1, min(100, (int)($_GET['limit'] ?? 30)));
        $sessionPath = $baseDir . DIRECTORY_SEPARATOR . $sessId;
        if (!is_dir($sessionPath)) jexit(['error'=>'session_not_found', 'entries'=>[], 'total'=>0, 'has_more'=>false]);
        $jsonPath = $sessionPath . DIRECTORY_SEPARATOR . 'metadata.json';
        $infoPath = $sessionPath . DIRECTORY_SEPARATOR . 'session_info.json';
        $entries = [];
        if (file_exists($jsonPath)) {
            $c = @file_get_contents($jsonPath);
            $p = $c ? json_decode($c, true) : [];
            if (is_array($p)) $entries = $p;
        }
        usort($entries, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
        $total = count($entries);
        $slice = array_slice($entries, $offset, $limit);
        $info = null;
        if (file_exists($infoPath)) {
            $c = @file_get_contents($infoPath);
            $p = $c ? json_decode($c, true) : null;
            if (is_array($p)) $info = $p;
        }
        jexit([
            'session_id' => $sessId,
            'info' => $info,
            'entries' => array_values($slice),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < $total,
        ]);
    }

    $sessions = [];
    if (is_dir($baseDir)) {
        foreach (scandir($baseDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $sessionPath = $baseDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($sessionPath)) continue;
            $jsonPath = $sessionPath . DIRECTORY_SEPARATOR . 'metadata.json';
            $infoPath = $sessionPath . DIRECTORY_SEPARATOR . 'session_info.json';
            $entries = []; $info = null;
            if (file_exists($jsonPath)) {
                $c = @file_get_contents($jsonPath);
                $p = $c ? json_decode($c, true) : [];
                if (is_array($p)) $entries = $p;
            }
            if (file_exists($infoPath)) {
                $c = @file_get_contents($infoPath);
                $p = $c ? json_decode($c, true) : null;
                if (is_array($p)) $info = $p;
            }
            if (empty($entries) && empty($info)) continue;
            $preview = [];
            $n = count($entries);
            for ($i = max(0, $n-3); $i < $n; $i++) {
                if (!empty($entries[$i]['server_url'])) $preview[] = $entries[$i]['server_url'];
                elseif (!empty($entries[$i]['agnes_url'])) $preview[] = $entries[$i]['agnes_url'];
            }
            $firstTs = $entries[0]['timestamp'] ?? ($info['timestamp'] ?? null);
            $sessions[$entry] = [
                'session_id' => $entry,
                'count' => count($entries),
                'mode' => $info['mode'] ?? 'unknown',
                'timestamp' => $firstTs,
                'preview' => $preview,
                'reference_url' => $info['reference_url'] ?? null,
                'text_prompt' => $info['text_prompt'] ?? '',
                'info' => $info,
            ];
        }
        $legacyJson = $baseDir . DIRECTORY_SEPARATOR . 'metadata.json';
        if (file_exists($legacyJson)) {
            $c = @file_get_contents($legacyJson); $p = $c ? json_decode($c, true) : [];
            if (is_array($p) && !empty($p)) {
                $preview = [];
                foreach (array_slice($p, -3) as $e) {
                    if (!empty($e['server_url'])) $preview[] = $e['server_url'];
                    elseif (!empty($e['agnes_url'])) $preview[] = $e['agnes_url'];
                }
                $sessions['legacy'] = [
                    'session_id' => 'legacy',
                    'count' => count($p),
                    'mode' => 'unknown',
                    'timestamp' => $p[0]['timestamp'] ?? null,
                    'preview' => $preview,
                    'reference_url' => null,
                    'text_prompt' => '',
                    'info' => null,
                ];
            }
        }
    }
    uksort($sessions, fn($a, $b) => strcmp($sessions[$b]['timestamp'] ?? '', $sessions[$a]['timestamp'] ?? ''));

    jexit([
        'ip' => $ip,
        'queue_count' => queueCountPending($ip),
        'sessions' => array_values($sessions),
    ]);
}

// ============================================================
// ENDPOINT : ?clear_queue=1
// ============================================================
if (isset($_GET['clear_queue'])) {
    $ip = getUserIp();
    queueClearAll($ip);
    jexit(['ok' => true, 'queue_count' => 0]);
}

// ============================================================
// ENDPOINT : ?process=1
// ============================================================
if (isset($_GET['process'])) {
    global $BATCH_MAX_DURATION, $BATCH_CURL_TIMEOUT;
    $ip = getUserIp();
    $rawKeys = array_filter(array_map('trim', explode("\n", $_POST['keys'] ?? '')));
    if (empty($rawKeys)) jexit(['error'=>'no_keys', 'results'=>[], 'remaining'=>0]);
    $keyState = syncAndLoadKeys($rawKeys);
    if (empty($keyState)) jexit(['error'=>'no_keys_db', 'results'=>[], 'remaining'=>0]);

    $totalRpm = 0;
    foreach ($keyState as $ks) $totalRpm += (int)$ks['rpm'];
    $autoLimit = max(2, min(15, (int)floor($totalRpm / 3)));
    $limit = isset($_POST['limit']) ? max(1, min(20, (int)$_POST['limit'])) : $autoLimit;

    $drain = queueDrain($ip, $limit);
    if (!empty($drain['locked'])) jexit(['results'=>[], 'remaining'=>$drain['remaining'] ?? 0, 'locked'=>true]);
    $items = $drain['items'];
    if (empty($items)) jexit(['results'=>[], 'remaining'=>0]);

    $tasks = [];
    foreach ($items as $it) $tasks[] = buildTaskFromQueueItem($it);

    $batchResults = runEventLoopBatch($keyState, $tasks, $BATCH_MAX_DURATION, $BATCH_CURL_TIMEOUT);

    $out = [];
    foreach ($batchResults as $br) {
        $task = $br['task'];
        $meta = $task['meta'] ?? [];
        $itemId = $meta['_item_id'] ?? null;
        $kind = $meta['_kind'] ?? 'style';
        $sessionId = $meta['_session_id'] ?? 'unknown';
        $code = $br['code'];
        $body = $br['body'];
        $timeout = $br['timeout'] ?? false;

        if ($code === 200) {
            $url = json_decode($body, true)['data'][0]['url'] ?? null;
            if ($url) {
                $serverUrl = saveImageToDisk($ip, $sessionId, $url, $meta['prompt'] ?? '', [
                    'kind' => $kind,
                    'source' => $meta['source'] ?? '',
                    'category' => $meta['category'] ?? '',
                    'style_id' => $meta['style_id'] ?? '',
                    'style_name' => $meta['style_name'] ?? '',
                    'faithful' => $meta['faithful'] ?? null,
                    'mode' => $meta['mode'] ?? '',
                    'panel_index' => $meta['index'] ?? null,
                    'title' => $meta['title'] ?? '',
                    'description' => $meta['description'] ?? '',
                    'character' => $meta['character'] ?? null,
                    'scene' => $meta['scene'] ?? null,
                    'parent_url' => $meta['parent_url'] ?? null,
                    'user_modification' => $meta['user_modification'] ?? null,
                    'text_prompt' => $meta['text_prompt'] ?? '',
                ]);
                queueMarkResult($ip, $itemId, true);
                $out[] = [
                    'status' => 'ok',
                    'kind' => $kind,
                    'meta' => $meta,
                    'url' => $url,
                    'server_url' => $serverUrl,
                    'item_id' => $itemId,
                ];
                continue;
            }
        }
        $errStr = $timeout ? 'timeout' : (string)($code ?: ($br['errno'] ?? 'error'));
        queueMarkResult($ip, $itemId, false, $errStr);
        $out[] = [
            'status' => 'error',
            'kind' => $kind,
            'meta' => $meta,
            'error' => $errStr,
            'code' => $code,
            'item_id' => $itemId,
        ];
    }

    jexit(['results'=>$out, 'remaining'=>queueCountPending($ip)]);
}

// ============================================================
// ENDPOINT : ?retry_one=1
// ============================================================
if (isset($_GET['retry_one'])) {
    global $RETRY_CURL_TIMEOUT;
    $ip = getUserIp();
    $itemId = $_POST['item_id'] ?? '';
    $rawKeys = array_filter(array_map('trim', explode("\n", $_POST['keys'] ?? '')));
    if (empty($rawKeys)) jexit(['ok'=>false, 'error'=>'no_keys']);
    if (empty($itemId)) jexit(['ok'=>false, 'error'=>'no_item_id']);

    $found = queueMarkUrgent($ip, $itemId);
    if (!$found) jexit(['ok'=>false, 'error'=>'item_not_found']);

    $keyState = syncAndLoadKeys($rawKeys);
    $task = buildTaskFromQueueItem($found);
    $batchResults = runEventLoopBatch($keyState, [$task], 90.0, $RETRY_CURL_TIMEOUT);

    $res = $batchResults[0] ?? null;
    if ($res && $res['code'] === 200) {
        $url = json_decode($res['body'], true)['data'][0]['url'] ?? null;
        if ($url) {
            $meta = $found['meta'] ?? [];
            $serverUrl = saveImageToDisk($ip, $found['session_id'] ?? 'unknown', $url, $meta['prompt'] ?? '', [
                'kind' => $found['kind'] ?? 'style',
                'source' => $meta['source'] ?? '',
                'category' => $meta['category'] ?? '',
                'style_id' => $meta['style_id'] ?? '',
                'style_name' => $meta['style_name'] ?? '',
                'faithful' => $meta['faithful'] ?? null,
                'mode' => $meta['mode'] ?? '',
                'panel_index' => $meta['index'] ?? null,
                'title' => $meta['title'] ?? '',
                'description' => $meta['description'] ?? '',
                'parent_url' => $meta['parent_url'] ?? null,
                'user_modification' => $meta['user_modification'] ?? null,
                'text_prompt' => $meta['text_prompt'] ?? '',
            ]);
            queueMarkResult($ip, $itemId, true);
            jexit([
                'ok' => true,
                'url' => $url,
                'server_url' => $serverUrl,
                'kind' => $found['kind'] ?? 'style',
                'meta' => $meta,
            ]);
        }
    }
    $errStr = ($res['timeout'] ?? false) ? 'timeout' : (string)($res['code'] ?? 'error');
    queueMarkResult($ip, $itemId, false, $errStr);
    jexit([
        'ok' => false,
        'error' => $errStr,
        'code' => $res['code'] ?? 0,
        'kind' => $found['kind'] ?? 'style',
        'meta' => $found['meta'] ?? [],
    ]);
}

// ============================================================
// ENDPOINT : ?modify=1 — img→txt puis txt→img (jamais img2img)
// ============================================================
if (isset($_GET['modify'])) {
    $ip = getUserIp();
    $rawKeys = array_filter(array_map('trim', explode("\n", $_POST['keys'] ?? '')));
    if (empty($rawKeys)) jexit(['ok'=>false, 'error'=>'no_keys']);

    $imageUrl = trim($_POST['image_url'] ?? '');
    $userPrompt = trim($_POST['prompt'] ?? '');
    $ratio = $_POST['ratio'] ?? '1:1';
    $parentItemId = $_POST['parent_item_id'] ?? '';
    $sessionId = safeSlug($_POST['session_id'] ?? ('mod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3))));

    if (!$imageUrl || !$userPrompt) jexit(['ok'=>false, 'error'=>'missing_params']);

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $imgData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$imgData) jexit(['ok'=>false, 'error'=>'download_failed']);

    $mime = 'image/png';
    if (strpos($imageUrl, '.jpg') !== false || strpos($imageUrl, '.jpeg') !== false) $mime = 'image/jpeg';
    elseif (strpos($imageUrl, '.webp') !== false) $mime = 'image/webp';
    $dataUri = 'data:' . $mime . ';base64,' . base64_encode($imgData);

    $keyState = syncAndLoadKeys($rawKeys);
    if (empty($keyState)) jexit(['ok'=>false, 'error'=>'no_keys_db']);

    $sysPrompt = "Analyze the visual style, technique, medium, lighting, and composition of this image. Return ONLY JSON: {\"style_description\":\"precise visual style in 2-3 sentences\",\"subject_summary\":\"brief subject\",\"medium\":\"photography/oil painting/etc\"}";
    $task = [
        'endpoint' => '/chat/completions',
        'body' => imgToTxt($dataUri, $sysPrompt, 'Analyse le style visuel.'),
        'meta' => [],
    ];
    $results = runEventLoopBatch($keyState, [$task], 60.0, 60.0);
    $res = $results[0] ?? null;
    if (!$res || $res['code'] !== 200) jexit(['ok'=>false, 'error'=>'analysis_failed', 'code'=>$res['code'] ?? 0]);
    $analysis = cleanJson(json_decode($res['body'], true)['choices'][0]['message']['content'] ?? '') ?: [];
    $styleDesc = $analysis['style_description'] ?? 'cinematic photograph';
    $subject = $analysis['subject_summary'] ?? '';

    $fullPrompt = "Preserve exact visual style: {$styleDesc}. Subject: {$subject}. CRITICAL MODIFICATION (highest priority): {$userPrompt}. Masterpiece, best quality, no text, no watermark";

    $meta = [
        'kind' => 'modify',
        'source' => 'modify',
        'prompt' => $fullPrompt,
        'user_modification' => $userPrompt,
        'parent_url' => $imageUrl,
        'parent_item_id' => $parentItemId,
        'session_id' => $sessionId,
        'mode' => 'modify',
        'category' => 'modify',
        'style_name' => 'Modification IA',
        'analysis' => $analysis,
        'text_prompt' => $userPrompt,
    ];
    $queueItem = makeQueueItem($sessionId, 'modify', txtToImg($fullPrompt, $ratio), $meta, 'current');
    queueAddMany($ip, [$queueItem]);

    jexit([
        'ok' => true,
        'item_id' => $queueItem['id'],
        'session_id' => $sessionId,
        'analysis' => $analysis,
        'prompt' => $fullPrompt,
    ]);
}

// ============================================================
// ENDPOINT : ?regenerate=1 — relance série similaire
// ============================================================
if (isset($_GET['regenerate'])) {
    global $MASTER_STYLES;
    $ip = getUserIp();
    $rawKeys = array_filter(array_map('trim', explode("\n", $_POST['keys'] ?? '')));
    if (empty($rawKeys)) jexit(['ok'=>false, 'error'=>'no_keys']);
    $oldSessionId = safeSlug($_POST['session_id'] ?? '');
    $ratio = $_POST['ratio'] ?? '1:1';
    if (!$oldSessionId) jexit(['ok'=>false, 'error'=>'no_session']);

    $info = readSessionInfo($ip, $oldSessionId);
    if (!$info) jexit(['ok'=>false, 'error'=>'session_not_found']);

    $character = $info['character'] ?? [];
    $scene = $info['scene'] ?? [];
    $style = $info['style'] ?? [];
    $mode = $info['mode'] ?? 'new_style';
    $refUrl = $info['reference_url'] ?? null;
    $textPrompt = $info['text_prompt'] ?? '';
    $hasImage = !empty($refUrl);

    $newSessionId = safeSlug('regen_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)));

    $charFull      = buildCharSummary($character);
    $charStrict    = buildCharacterIdentityOnly($character);
    $sceneFull     = buildSceneFull($scene);
    $sceneDecor    = buildSceneDecor($scene);
    $sceneStrict   = buildSceneLocationOnly($scene);
    $sceneLighting = buildSceneLighting($scene);
    $sceneMood     = buildSceneMood($scene);
    $sceneCompos   = buildSceneComposition($scene);
    $styleDesc     = buildStyleDescription($style);
    $userDirective = buildUserDirective($textPrompt, $hasImage);

    $queueItems = [];
    foreach ($MASTER_STYLES as $i => $style_item) {
        if (!empty($style_item['faithful'])) {
            $photoType = $style_item['photo_type'] ?? 'cinematic photograph';
            $stylePart = $styleDesc !== '' ? "original visual style: {$styleDesc}. " : "";
            $prompt = "{$photoType}. EXACT faithful reproduction. Preserve exact original visual style, medium, technique, color palette, lighting rendering, texture, and composition. No stylization. " .
                      $stylePart . "Subject: {$charFull}. Scene: {$sceneFull}.{$userDirective} Lighting: {$sceneLighting}. Composition: {$sceneCompos}. Mood: {$sceneMood}. Ultra detailed, high fidelity, exact match, no text, no watermark";
        } else {
            if ($mode === 'keep_style') {
                $prompt = "{$style_item['name']} STYLE ARTWORK — dominant. {$style_item['prompt_body']}. Subject: {$charFull}. Setting: {$sceneDecor}.{$userDirective} Lighting: {$sceneLighting}. Mood: {$sceneMood}. Cinematic composition, masterpiece, unmistakably {$style_item['name']}";
            } else {
                $prompt = "{$style_item['name']} STYLE ARTWORK — DOMINANT and UNMISTAKABLE. {$style_item['prompt_body']}. CRITICAL: The visual medium, technique, color palette, lighting, texture, line quality, composition, mood, and atmosphere are 100% defined by {$style_item['name']}. NO element of the source image's original style is preserved. Result must look like pure {$style_item['name']} art. Subject identity (face, body, hair, clothing): {$charStrict}. Physical location: {$sceneStrict}.{$userDirective} Rendered exclusively as {$style_item['name']}, masterpiece";
            }
        }
        $meta = [
            'index'      => $i,
            'source'     => 'master',
            'category'   => $style_item['category'],
            'style_id'   => $style_item['id'],
            'style_name' => $style_item['name'],
            'style_body' => $style_item['prompt_body'] ?? '',
            'prompt'     => $prompt,
            'faithful'   => !empty($style_item['faithful']),
            'mode'       => $mode,
            'character'  => $character,
            'scene'      => $scene,
            'session_id' => $newSessionId,
            'text_prompt' => $textPrompt,
        ];
        $queueItems[] = makeQueueItem($newSessionId, 'style', txtToImg($prompt, $ratio), $meta, 'current');
    }

    saveSessionInfo($ip, $newSessionId, $character, $scene, $style, $mode, $refUrl, $textPrompt);
    queueReplaceAllPending($ip, $queueItems);

    jexit([
        'ok' => true,
        'session_id' => $newSessionId,
        'total' => count($queueItems),
        'items' => array_map(fn($qi) => [
            'item_id' => $qi['id'],
            'style_id' => $qi['meta']['style_id'],
            'style_name' => $qi['meta']['style_name'],
            'category' => $qi['meta']['category'],
            'faithful' => $qi['meta']['faithful'],
            'prompt' => $qi['meta']['prompt'],
            'text_prompt' => $qi['meta']['text_prompt'],
        ], $queueItems),
    ]);
}

// ============================================================
// ENDPOINT : ?stream=1
// ============================================================
if (isset($_GET['stream'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    set_time_limit(0);

    $rawKeys = array_filter(array_map('trim', explode("\n", $_POST['keys'] ?? '')));
    if (empty($rawKeys)) { sse('error', ['msg'=>'Aucune clé API fournie.']); exit; }
    $keyState = syncAndLoadKeys($rawKeys);
    if (empty($keyState)) { sse('error', ['msg'=>'Aucune clé valide.']); exit; }

    $userIp    = getUserIp();
    $action    = $_POST['action'] ?? 'generate';
    $ratio     = $_POST['ratio'] ?? '1:1';
    $mode      = $_POST['mode'] ?? 'new_style';
    if (!in_array($mode, ['keep_style','new_style'])) $mode = 'new_style';
    $sessionId = safeSlug($_POST['session_id'] ?? ('sess_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4))));
    $textPrompt = trim($_POST['text_prompt'] ?? '');

    // ===================================================
    // ACTION generate (image et/ou texte)
    // ===================================================
    if ($action === 'generate') {
        global $MASTER_STYLES;
        $hasImage = isset($_FILES['ref']) && $_FILES['ref']['error'] === UPLOAD_ERR_OK;
        $hasText = $textPrompt !== '';

        if (!$hasImage && !$hasText) {
            sse('error', ['msg'=>'Fournissez une image ou un prompt texte.']);
            exit;
        }

        sse('session_created', ['session_id'=>$sessionId, 'mode'=>$mode]);
        sse('styles_preset_list', ['styles'=>array_map(function($s){
            return ['id'=>$s['id'], 'name'=>$s['name'], 'category'=>$s['category'], 'faithful'=>!empty($s['faithful'])];
        }, $MASTER_STYLES)]);

        $character = []; $scene = []; $style = []; $referenceUrl = null;

        if ($hasImage) {
            $mime = mime_content_type($_FILES['ref']['tmp_name']);
            $referenceUrl = saveReferenceImage($userIp, $sessionId, $_FILES['ref']['tmp_name'], $mime);
            $refData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($_FILES['ref']['tmp_name']));

            sse('phase', ['step'=>'analysis', 'msg'=>$hasText ? '7 analyses image + directive texte...' : '7 analyses image en cours...']);
            $analysisResults = []; $analysisDone = 0; $ANALYSIS_TOTAL = 7;

            // Contexte additionnel injecté dans chaque prompt d'analyse
            $extraCtx = $hasText ? "\n\nUSER ADDITIONAL CONTEXT (must be considered but should NOT override the analyzed subject/scene): \"" . $textPrompt . "\"" : '';

            $charPrompts = [
                "Return ONLY JSON: {\"age_range\":\"28-32\",\"gender_presentation\":\"male/female/androgynous\",\"ethnicity_primary\":\"SPECIFIC sub-ethnicity\",\"ethnicity_secondary\":\"if mixed\",\"skin_undertone\":\"warm/cool/neutral/olive\",\"skin_tone\":\"specific\",\"skin_texture\":\"smooth/freckled/weathered\",\"epicanthic_fold\":\"present/absent/partial\",\"eye_color\":\"specific\",\"eye_shape\":\"monolid/almond/round/hooded/upturned\",\"nose_bridge_height\":\"low/medium/high\",\"nose_width\":\"narrow/medium/wide\",\"lip_thickness\":\"thin/medium/full\",\"hair_texture\":\"1a-4c\",\"hair_color\":\"with undertone\",\"hair_style\":\"specific\",\"distinguishing_marks\":[\"scars,freckles\"],\"body_type\":\"slim/athletic/curvy/heavyset/muscular\"}" . $extraCtx,
                "Return ONLY JSON: {\"face_shape\":\"oval/round/square/heart/diamond/oblong\",\"jawline\":\"sharp/soft/angular/square\",\"cheekbones\":\"prominent/subtle/high/low\",\"forehead\":\"high/medium/low\",\"eyebrows_thickness\":\"thin/medium/thick\",\"eyebrows_shape\":\"arched/straight/curved/angled\",\"eyelashes\":\"short/medium/long\",\"facial_hair_pattern\":\"none/stubble/goatee/full beard/mustache\",\"expression\":\"current expression\",\"facial_proportions\":\"balance\"}" . $extraCtx,
                "Return ONLY JSON: {\"clothing_style\":\"casual/formal/streetwear/vintage/minimalist/boho/athletic/business\",\"clothing_top\":\"item with color\",\"clothing_bottom\":\"specific\",\"clothing_footwear\":\"specific\",\"outerwear\":\"if any\",\"accessories\":[\"items\"],\"fashion_era\":\"contemporary/90s/vintage/retro/futuristic/timeless\",\"body_language\":\"confident/relaxed/tense/shy/powerful/gentle/weary\",\"pose\":\"description\",\"overall_vibe\":\"2-3 sentences\"}" . $extraCtx,
            ];
            $scenePrompts = [
                "Return ONLY JSON: {\"setting_type\":\"indoor/outdoor/semi-indoor/vehicle\",\"location_specific\":\"VERY specific\",\"location_era\":\"contemporary/historical/futuristic/post-apocalyptic\",\"architecture_style\":\"if visible\",\"furnishings\":[\"items\"],\"cultural_markers\":[\"elements\"],\"weather\":\"if outdoor\",\"time_of_day\":\"golden hour/blue hour/midday/etc\",\"season\":\"if discernible\",\"atmosphere_texture\":\"hazy/clear/dusty/rainy/smoky/misty\",\"additional_elements\":[\"any extra subjects/objects mentioned by user\"]}" . $extraCtx,
                "Return ONLY JSON: {\"lighting_type\":\"natural/artificial/mixed/practical\",\"lighting_source\":\"specific\",\"lighting_direction\":\"front/side/back/top/ambient\",\"lighting_quality\":\"soft/hard/diffused/contrasty/dappled\",\"color_temperature\":\"warm/cool/neutral/mixed\",\"key_light_color\":\"specific\",\"shadow_character\":\"hard/soft/minimal\",\"composition\":\"rule of thirds/centered/symmetrical/golden ratio/diagonal\",\"camera_angle\":\"eye level/low/high/dutch/bird's eye\",\"focal_length_estimate\":\"wide/normal/tele\",\"depth_of_field\":\"shallow/medium/deep\"}" . $extraCtx,
                "Return ONLY JSON: {\"emotional_tone\":\"specific mood\",\"cinematic_genre_hint\":\"thriller/drama/romance/horror/comedy/noir/sci-fi/fantasy\",\"color_palette\":\"dominant colors\",\"color_grading_hint\":\"typical grading\",\"narrative_context\":\"what's happening\",\"story_arcs\":[\"3-4\"],\"reference_films\":[\"2-3\"]}" . $extraCtx,
            ];
            $stylePrompts = [
                "Analyze ONLY the visual and artistic STYLE (NOT subject, NOT scene). Return ONLY JSON: {\"medium\":\"photography/oil painting/watercolor/digital painting/3D render/manga/comic/poster/pixel art/charcoal/etc\",\"technique\":\"specific\",\"color_palette\":\"specific colors with names\",\"color_grading\":\"warm/cool/muted/saturated/monochrome/etc\",\"lighting_style\":\"hard/soft/chiaroscuro/flat/volumetric\",\"texture\":\"grain/brushstrokes/clean lines/pixels/smooth/etc\",\"line_quality\":\"if applicable\",\"composition_style\":\"how space is organized\",\"art_movement\":\"specific movement if any\",\"era\":\"decade or period\",\"reference_artists\":[\"artists with this style\"],\"overall_style_description\":\"2-3 sentences describing the exact visual style\"}",
            ];

            $analysisTasks = [];
            $mk = function($p, $key, $txt) use ($refData, &$analysisResults, &$analysisDone, $ANALYSIS_TOTAL) {
                return [
                    'endpoint' => '/chat/completions',
                    'body' => imgToTxt($refData, $p, $txt),
                    'meta' => ['key'=>$key],
                    'onComplete' => function($b, $c, $m) use (&$analysisResults, &$analysisDone, $ANALYSIS_TOTAL) {
                        $analysisDone++;
                        if ($c === 200) {
                            $d = cleanJson(json_decode($b, true)['choices'][0]['message']['content'] ?? '');
                            if (is_array($d)) $analysisResults[$m['key']] = $d;
                        }
                        sse('analysis_progress', ['done'=>$analysisDone, 'total'=>$ANALYSIS_TOTAL, 'key'=>$m['key']]);
                        return [];
                    }
                ];
            };
            foreach ($charPrompts as $i => $p) $analysisTasks[] = $mk($p, 'char_'.$i, 'Analyse.');
            foreach ($scenePrompts as $i => $p) $analysisTasks[] = $mk($p, 'scene_'.$i, 'Analyse.');
            foreach ($stylePrompts as $i => $p) $analysisTasks[] = $mk($p, 'style_'.$i, 'Analyse le style.');

            runEventLoop($keyState, $analysisTasks);

            foreach (['char_0','char_1','char_2'] as $k) if (!empty($analysisResults[$k])) $character = array_merge($character, $analysisResults[$k]);
            foreach (['scene_0','scene_1','scene_2'] as $k) if (!empty($analysisResults[$k])) $scene = array_merge($scene, $analysisResults[$k]);
            if (!empty($analysisResults['style_0'])) $style = $analysisResults['style_0'];

            if ($hasText) {
                $scene['user_addition'] = $textPrompt;
            }
        } else {
            // TEXTE-ONLY
            sse('phase', ['step'=>'analysis', 'msg'=>'Analyse du prompt texte...']);
            $sysParse = "Extract structured info from the user's prompt. Return ONLY JSON: {\"character\":{\"age_range\":\"\",\"gender_presentation\":\"\",\"ethnicity_primary\":\"\",\"skin_tone\":\"\",\"hair_color\":\"\",\"hair_style\":\"\",\"eye_color\":\"\",\"body_type\":\"\",\"clothing_top\":\"\",\"clothing_bottom\":\"\",\"accessories\":[],\"overall_vibe\":\"\"},\"scene\":{\"location_specific\":\"\",\"setting_type\":\"\",\"architecture_style\":\"\",\"time_of_day\":\"\",\"weather\":\"\",\"season\":\"\",\"lighting_type\":\"\",\"lighting_quality\":\"\",\"color_temperature\":\"\",\"emotional_tone\":\"\",\"cinematic_genre_hint\":\"\",\"color_palette\":\"\",\"composition\":\"\"}}";
            $task = [
                'endpoint' => '/chat/completions',
                'body' => [
                    'model' => $MODEL_TXT, 'temperature' => 0.3, 'max_tokens' => 800,
                    'messages' => [
                        ['role'=>'system','content'=>$sysParse],
                        ['role'=>'user','content'=>$textPrompt]
                    ]
                ],
                'meta' => [],
            ];
            $results = runEventLoopBatch($keyState, [$task], 45.0, 60.0);
            $r = $results[0] ?? null;
            if ($r && $r['code'] === 200) {
                $parsed = cleanJson(json_decode($r['body'], true)['choices'][0]['message']['content'] ?? '') ?: [];
                $character = $parsed['character'] ?? [];
                $scene = $parsed['scene'] ?? [];
            }
            $style = ['overall_style_description' => 'derived from user description'];
            sse('analysis_progress', ['done'=>1, 'total'=>1, 'key'=>'text']);
        }

        sse('analysis_complete', ['character'=>$character, 'scene'=>$scene, 'style'=>$style, 'reference_url'=>$referenceUrl]);
        saveSessionInfo($userIp, $sessionId, $character, $scene, $style, $mode, $referenceUrl, $textPrompt);

        $charFull      = buildCharSummary($character);
        $charStrict    = buildCharacterIdentityOnly($character);
        $sceneFull     = buildSceneFull($scene);
        $sceneDecor    = buildSceneDecor($scene);
        $sceneStrict   = buildSceneLocationOnly($scene);
        $sceneLighting = buildSceneLighting($scene);
        $sceneMood     = buildSceneMood($scene);
        $sceneCompos   = buildSceneComposition($scene);
        $styleDesc     = buildStyleDescription($style);
        $userDirective = buildUserDirective($textPrompt, $hasImage);

        sse('phase', ['step'=>'queue', 'msg'=>"Construction de la queue (mode: {$mode})..."]);

        $queueItems = [];
        foreach ($MASTER_STYLES as $i => $style_item) {
            if (!empty($style_item['faithful'])) {
                $photoType = $style_item['photo_type'] ?? 'cinematic photograph';
                $stylePart = $styleDesc !== '' ? "original visual style: {$styleDesc}. " : "";
                $prompt = "{$photoType}. EXACT faithful reproduction. Preserve exact original visual style, medium, technique, color palette, lighting rendering, texture, and composition. No stylization. " .
                          $stylePart . "Subject: {$charFull}. Scene: {$sceneFull}.{$userDirective} Lighting: {$sceneLighting}. Composition: {$sceneCompos}. Mood: {$sceneMood}. Ultra detailed, high fidelity, exact match, no text, no watermark";
            } else {
                if ($mode === 'keep_style') {
                    $prompt = "{$style_item['name']} STYLE ARTWORK — dominant. {$style_item['prompt_body']}. Subject: {$charFull}. Setting: {$sceneDecor}.{$userDirective} Lighting: {$sceneLighting}. Mood: {$sceneMood}. Cinematic composition, masterpiece, unmistakably {$style_item['name']}";
                } else {
                    $prompt = "{$style_item['name']} STYLE ARTWORK — DOMINANT and UNMISTAKABLE. {$style_item['prompt_body']}. CRITICAL: The visual medium, technique, color palette, lighting, texture, line quality, composition, mood, and atmosphere are 100% defined by {$style_item['name']}. NO element of the source image's original style is preserved. Result must look like pure {$style_item['name']} art. Subject identity (face, body, hair, clothing): {$charStrict}. Physical location: {$sceneStrict}.{$userDirective} Rendered exclusively as {$style_item['name']}, masterpiece";
                }
            }
            $meta = [
                'index'      => $i,
                'source'     => 'master',
                'category'   => $style_item['category'],
                'style_id'   => $style_item['id'],
                'style_name' => $style_item['name'],
                'style_body' => $style_item['prompt_body'] ?? '',
                'prompt'     => $prompt,
                'faithful'   => !empty($style_item['faithful']),
                'mode'       => $mode,
                'character'  => $character,
                'scene'      => $scene,
                'session_id' => $sessionId,
                'text_prompt' => $textPrompt,
            ];
            $queueItems[] = makeQueueItem($sessionId, 'style', txtToImg($prompt, $ratio), $meta, 'current');
        }
        // Priorité : remplace le pending (garde processing) → nouvelle série prioritaire
        queueReplaceAllPending($userIp, $queueItems);

        $itemMap = [];
        foreach ($queueItems as $qi) {
            $itemMap[] = [
                'item_id'    => $qi['id'],
                'style_id'   => $qi['meta']['style_id'],
                'style_name' => $qi['meta']['style_name'],
                'category'   => $qi['meta']['category'],
                'faithful'   => $qi['meta']['faithful'],
                'prompt'     => $qi['meta']['prompt'],
                'text_prompt' => $qi['meta']['text_prompt'],
            ];
        }
        sse('queued', [
            'session_id' => $sessionId,
            'total' => count($queueItems),
            'kind' => 'style',
            'mode' => $mode,
            'character' => $character,
            'scene' => $scene,
            'style' => $style,
            'reference_url' => $referenceUrl,
            'text_prompt' => $textPrompt,
            'items' => $itemMap,
        ]);
        sse('done', ['msg' => count($queueItems) . ' images enqueued.', 'session_id' => $sessionId]);
        exit;
    }

    // ===================================================
    // ACTION storyboard
    // ===================================================
    if ($action === 'storyboard') {
        global $PRESET_STORYBOARDS;

        $character  = json_decode($_POST['character_json'] ?? '{}', true) ?: [];
        $scene      = json_decode($_POST['scene_json'] ?? '{}', true) ?: [];
        $mainStyle  = $_POST['main_style'] ?? '';
        $mainPrompt = $_POST['main_prompt'] ?? '';
        $userTextPrompt = trim($_POST['user_text_prompt'] ?? '');

        $isFaithfulStyle = (strpos($mainStyle, 'Fidèle') === 0);
        if (empty($mainPrompt) || $isFaithfulStyle) {
            $sessInfo = readSessionInfo($userIp, $sessionId);
            $sessStyle = $sessInfo['style'] ?? [];
            $sessStyleDesc = buildStyleDescription($sessStyle);
            if ($sessStyleDesc !== '') {
                $mainPrompt = $sessStyleDesc;
                if ($isFaithfulStyle) $mainStyle = 'faithful reproduction of the original';
            } else {
                $mainPrompt = 'cinematic photography with defined lighting and composition';
            }
        }

        if ($mode === 'keep_style') {
            $styleLock = "STRICT STYLE: {$mainStyle} style artwork — dominant. {$mainPrompt}. The entire visual rendering must be unmistakably {$mainStyle}. ";
        } else {
            $styleLock = "STRICT STYLE: {$mainStyle} style artwork — DOMINANT and UNMISTAKABLE. {$mainPrompt}. The entire visual rendering (medium, palette, lighting, texture, line quality, atmosphere, mood) is 100% defined by {$mainStyle}. No element of any other style is preserved. Every scene below MUST be rendered as pure {$mainStyle} art. ";
        }
        if ($userTextPrompt !== '') {
            $styleLock .= "ALSO INCLUDE (user's additional element, must be visible in every panel): \"{$userTextPrompt}\". ";
        }

        if ($mode === 'keep_style') {
            $charSummary   = buildCharSummary($character);
            $sceneLighting = buildSceneLighting($scene);
            $sceneMood     = buildSceneMood($scene);
            $sceneContext  = buildSceneContext($scene);
        } else {
            $charSummary   = buildCharacterIdentityOnly($character);
            $sceneLighting = 'lighting defined by the style';
            $sceneMood     = 'mood defined by the style';
            $sceneContext  = buildSceneLocationOnly($scene);
        }

        $panels = [];
        foreach ($PRESET_STORYBOARDS as $i => $preset) {
            $panels[] = ['index'=>$i, 'source'=>'preset', 'title'=>$preset['title'], 'description'=>$preset['desc']];
        }
        sse('sb_script', ['panels'=>$panels, 'mode'=>$mode]);
        sse('phase', ['step'=>'sb_llm', 'msg'=>'LLM : génération des 20 scènes IA...']);

        $existingTitles = array_map(fn($p) => $p['title'], $panels);
        $aiSysSb = "Tu es un scénariste storyboarder.
SUJET : {$charSummary}
CONTEXTE : {$sceneContext}
LUMIÈRE : {$sceneLighting}
AMBIANCE : {$sceneMood}
MODE : {$mode}" .
($userTextPrompt !== '' ? "\nINSTRUCTION ADDITIONNELLE (doit apparaître dans TOUTES les scènes) : {$userTextPrompt}" : "") . "

30 scènes DÉJÀ utilisées (NE PAS DUPLIQUER) : " . json_encode($existingTitles) . "

MISSION : 20 scènes ORIGINALES.
RÈGLES : varie situations/plans/lieux, 1 à 3 personnages, action, ÉVITE les doublons." .
($mode === 'new_style' ? "\nMODE NEW STYLE : ne décris JAMAIS lumière/mood/palette." : "") . "

Réponds UNIQUEMENT en JSON strict : [{\"title\":\"...\",\"description\":\"...\",\"prompt\":\"40-70 words\"}, ... 20 items]";

        $aiScenes = [];
        $aiTask = [
            'endpoint' => '/chat/completions',
            'body' => [
                'model' => $MODEL_TXT, 'temperature' => 0.95, 'max_tokens' => 6000,
                'messages' => [['role'=>'system','content'=>$aiSysSb], ['role'=>'user','content'=>'Génère les 20 scènes.']]
            ],
            'meta' => [],
            'onComplete' => function($b, $c, $m) use (&$aiScenes) {
                if ($c === 200) {
                    $aiScenes = cleanJson(json_decode($b, true)['choices'][0]['message']['content'] ?? '[]') ?: [];
                }
                return [];
            }
        ];
        runEventLoop($keyState, [$aiTask]);

        $fallbacks = [
            ['title'=>'31 — Chase Through Market','description'=>'Poursuite marché','prompt'=>"dynamic tracking shot of {$charSummary} weaving through a bustling crowded market, motion blur, {$sceneLighting}, {$sceneMood}, adrenaline"],
            ['title'=>'32 — Ambush in Alley','description'=>'Embuscade ruelle','prompt'=>"tense medium shot of {$charSummary} ambushed in a narrow alley by two shadowy figures, {$sceneLighting}, {$sceneMood}, dramatic tension"],
            ['title'=>'33 — Romantic Dinner','description'=>'Dîner romantique','prompt'=>"intimate two-shot of {$charSummary} at a candlelit dinner with a partner, {$sceneLighting}, {$sceneMood}, gentle smiles"],
            ['title'=>'34 — Old Mentor Advice','description'=>'Conseil mentor','prompt'=>"tender two-shot of {$charSummary} listening to an elderly mentor, {$sceneLighting}, {$sceneMood}, generational bond"],
            ['title'=>'35 — Discovering a Map','description'=>'Découverte carte','prompt'=>"dramatic close-up of {$charSummary} unrolling an old map, wide eyes, {$sceneLighting}, {$sceneMood}, adventure"],
            ['title'=>'36 — Dance With Stranger','description'=>'Danse inconnu','prompt'=>"dynamic medium shot of {$charSummary} dancing with a mysterious stranger, motion, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'37 — Fighting Antagonist','description'=>'Combat','prompt'=>"dynamic action shot of {$charSummary} in physical struggle with a menacing antagonist, motion blur, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'38 — Crying on Shoulder','description'=>'Pleurs ami','prompt'=>"emotional two-shot of {$charSummary} crying on a close friend's shoulder, {$sceneLighting}, {$sceneMood}, intimacy"],
            ['title'=>'39 — Playing With Dog','description'=>'Chien','prompt'=>"warm medium shot of {$charSummary} playing joyfully with a dog in a park, {$sceneLighting}, {$sceneMood}, warmth"],
            ['title'=>'40 — Reading Old Letter','description'=>'Lettre','prompt'=>"close-medium shot of {$charSummary} reading a weathered letter, emotional, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'41 — Watching the Stars','description'=>'Étoiles','prompt'=>"wide low-angle shot of {$charSummary} lying on the ground stargazing next to a friend, {$sceneLighting}, {$sceneMood}, wonder"],
            ['title'=>'42 — Running From Danger','description'=>'Fuir','prompt'=>"dynamic tracking shot of {$charSummary} running away from danger, panicked, motion blur, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'43 — Morning Coffee','description'=>'Café matin','prompt'=>"intimate medium shot of {$charSummary} making morning coffee, sunlight through window, {$sceneLighting}, {$sceneMood}, stillness"],
            ['title'=>'44 — Photo With Friends','description'=>'Photo amis','prompt'=>"warm group shot of {$charSummary} posing for a photo with old friends, laughter, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'45 — Standing in Rain','description'=>'Sous la pluie','prompt'=>"medium shot of {$charSummary} standing still in heavy rain, soaked, {$sceneLighting}, {$sceneMood}, poetry"],
            ['title'=>'46 — Farewell at Station','description'=>'Adieu gare','prompt'=>"emotional two-shot of {$charSummary} embracing someone at a train station, tears, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'47 — Unexpected Reunion','description'=>'Retrouvailles','prompt'=>"surprised medium shot of {$charSummary} spotting an old friend unexpectedly, wide eyes, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'48 — At Crossroads','description'=>'Carrefour','prompt'=>"wide establishing shot of {$charSummary} standing at a crossroads, deciding, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'49 — Caring for Wounded','description'=>'Soigner','prompt'=>"tender medium shot of {$charSummary} tending to a wounded person, compassion, {$sceneLighting}, {$sceneMood}"],
            ['title'=>'50 — Final Victory','description'=>'Victoire','prompt'=>"epic wide shot of {$charSummary} celebrating a hard-won victory, arms raised, {$sceneLighting}, {$sceneMood}, triumphant"],
        ];
        $fi = 0;
        while (count($aiScenes) < 20 && $fi < count($fallbacks)) $aiScenes[] = $fallbacks[$fi++];
        $aiScenes = array_slice($aiScenes, 0, 20);

        $appendPanels = [];
        foreach ($aiScenes as $j => $s) {
            $appendPanels[] = ['index'=>30+$j, 'source'=>'ai', 'title'=>$s['title'] ?? ('AI Scene ' . ($j+1)), 'description'=>$s['description'] ?? ''];
        }
        sse('sb_script_append', ['panels'=>$appendPanels]);
        sse('phase', ['step'=>'queue', 'msg'=>'Construction de la queue storyboard (50 panneaux)...']);

        $queueItems = [];
        $allScenes = [];
        foreach ($PRESET_STORYBOARDS as $i => $preset) {
            $substituted = str_replace(
                ['{CHAR}','{LIGHTING}','{MOOD}','{SCENE}','{EMOTION}'],
                [$charSummary, $sceneLighting, $sceneMood, $sceneContext, 'emotional'],
                $preset['template']
            );
            $allScenes[] = [
                'index' => $i, 'source' => 'preset',
                'title' => $preset['title'], 'description' => $preset['desc'],
                'full_prompt' => $styleLock . $substituted,
            ];
        }
        foreach ($aiScenes as $j => $s) {
            $allScenes[] = [
                'index' => 30 + $j, 'source' => 'ai',
                'title' => $s['title'] ?? ('AI Scene ' . ($j+1)),
                'description' => $s['description'] ?? '',
                'full_prompt' => $styleLock . ($s['prompt'] ?? ''),
            ];
        }

        foreach ($allScenes as $sc) {
            $meta = [
                'index' => $sc['index'],
                'source' => $sc['source'],
                'title' => $sc['title'],
                'description' => $sc['description'],
                'prompt' => $sc['full_prompt'],
                'style_name' => $mainStyle,
                'mode' => $mode,
                'category' => 'storyboard',
                'session_id' => $sessionId,
                'text_prompt' => $userTextPrompt,
            ];
            $queueItems[] = makeQueueItem($sessionId, 'storyboard', txtToImg($sc['full_prompt'], $ratio), $meta, 'current');
        }
        // Priorité : remplace le pending → storyboard prioritaire sur les anciennes séries
        queueReplaceAllPending($userIp, $queueItems);

        $itemMap = [];
        foreach ($queueItems as $qi) {
            $itemMap[] = [
                'item_id' => $qi['id'],
                'index' => $qi['meta']['index'],
                'title' => $qi['meta']['title'],
                'source' => $qi['meta']['source'],
                'prompt' => $qi['meta']['prompt'],
            ];
        }
        sse('queued', [
            'session_id' => $sessionId,
            'total' => count($queueItems),
            'kind' => 'storyboard',
            'mode' => $mode,
            'items' => $itemMap,
        ]);
        sse('done', ['msg' => count($queueItems) . ' panneaux enqueued.', 'session_id' => $sessionId]);
        exit;
    }
}

// ============================================================
// CACHE-BUSTING ASSETS
// ============================================================
$CSS_VER = @filemtime(__DIR__ . '/style.css') ?: time();
$JS_VER  = @filemtime(__DIR__ . '/javascript.js') ?: time();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cinematic Storyboard Pro v18</title>
<link rel="stylesheet" href="style.css?v=<?= (int)$CSS_VER ?>">
</head>
<body>
<div class="container">
    <!-- HEADER -->
    <div class="header">
        <div class="logo">Cinematic<span>Storyboard v18</span><span class="badge-lib">analyse · LIFO · img→txt→img</span></div>
        <button class="settings-btn" onclick="toggleConfig()">⚙️ Clés API</button>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="generate" onclick="switchTab('generate')">🎨 Génération</button>
        <button class="tab-btn" data-tab="gallery" onclick="switchTab('gallery')">📷 Ma galerie <span class="queue-badge" id="queueBadge" style="display:none;">0</span></button>
    </div>

    <!-- TAB GENERATE -->
    <div class="tab-panel active" id="tab-generate">
        <div class="config-panel" id="configPanel">
            <h3>Clés API Agnes — format <code>clé</code> ou <code>clé:rpm</code></h3>
            <p>RPM = requêtes par minute (défaut 10). Ex: <code>sk-xxx:60</code>. Les clés sont stockées en SQLite et distribuées intelligemment (load-balancing RPM).</p>
            <textarea id="apiKeyArea" placeholder="sk-abc123:10&#10;sk-def456:60&#10;sk-ghi789"></textarea>
            <button onclick="saveKeys()">Sauvegarder</button>
            <div class="stat" id="keyStat"></div>
        </div>

        <div class="queue-banner" id="queueBanner">
            <span id="queueBannerText">0</span>
            <div class="qb-actions">
                <button class="resume" onclick="resumeQueue()">▶ Continuer</button>
                <button class="clear" onclick="clearQueue()">🗑 Vider</button>
            </div>
        </div>

        <div class="upload-card" id="dropZone">
            <h2>🎬 Décrivez ou déposez</h2>
            <p>Image de référence, prompt texte, ou les deux combinés</p>
            <input type="file" id="fileInput" accept="image/*" hidden>
            <img id="preview" style="display:none" alt="">
            <div class="upload-actions" onclick="event.stopPropagation()">
                <button onclick="document.getElementById('fileInput').click()">📤 Choisir une image</button>
                <button onclick="clearImage()" id="clearImgBtn" style="display:none;">✕ Retirer l'image</button>
            </div>
            <div class="text-prompt-section" onclick="event.stopPropagation()">
                <label>💬 Prompt texte (optionnel)</label>
                <textarea id="textPrompt" placeholder="Ex: elle est avec un robot / un samouraï âgé aux yeux perçants, kimono rouge délavé, temple en bambou sous la pluie..."></textarea>
                <div class="hint">💡 Combiné à une image, ce texte <strong>s'ajoute</strong> à la scène analysée (elle reste préservée). Seul, il décrit la scène.</div>
            </div>
            <div class="mode-selector" onclick="event.stopPropagation()">
                <label class="active" data-mode="new_style">
                    <input type="radio" name="mode" value="new_style" checked>
                    🎭 Nouveau style total
                    <span class="mode-help">Style source ignoré. Style choisi DOMINANT.</span>
                </label>
                <label data-mode="keep_style">
                    <input type="radio" name="mode" value="keep_style">
                    🎨 Garder le style source
                    <span class="mode-help">Style, lumière, ambiance préservés.</span>
                </label>
            </div>
            <div class="controls">
                <select id="ratio">
                    <option value="1:1">Carré 1:1</option>
                    <option value="4:3">Paysage 4:3</option>
                    <option value="16:9">Cinéma 16:9</option>
                    <option value="3:4">Portrait 3:4</option>
                    <option value="9:16">Story 9:16</option>
                    <option value="21:9">Ultra-wide 21:9</option>
                </select>
                <button id="generateBtn" disabled>Générer les 90 styles</button>
            </div>
        </div>

        <div class="progress-bar" id="progressBar"><div class="fill" id="progressFill"></div></div>
        <div class="status-text" id="statusText"></div>

        <div class="analysis-summary" id="analysisSummary">
            <div class="analysis-card">
                <h4>👤 Personnage <span class="analysis-progress-indicator" id="charProgress">0/3</span></h4>
                <div class="row" id="charRow"></div>
            </div>
            <div class="analysis-card">
                <h4>🎬 Scène <span class="analysis-progress-indicator" id="sceneProgress">0/3</span></h4>
                <div class="row" id="sceneRow"></div>
            </div>
            <div class="analysis-card">
                <h4>🎨 Style <span class="analysis-progress-indicator" id="styleProgress">0/1</span></h4>
                <div class="row" id="styleRow"></div>
            </div>
        </div>

        <div class="section-header" id="faithfulHeader" style="display:none;">
            <div class="section-title faithful"><span class="dot"></span> 10 images fidèles</div>
            <div class="section-count" id="faithfulCount">0 / 10</div>
        </div>
        <div class="style-gallery" id="faithfulGallery"></div>

        <div class="section-header" id="transformHeader" style="display:none;">
            <div class="section-title transform"><span class="dot"></span> 80 styles transformateurs</div>
            <div class="section-count" id="transformCount">0 / 80</div>
        </div>
        <div class="style-gallery" id="transformGallery"></div>
    </div>

    <!-- TAB GALLERY -->
    <div class="tab-panel" id="tab-gallery">
        <div class="gallery-toolbar">
            <h2>📷 Ma galerie</h2>
            <div class="gt-actions">
                <button onclick="refreshGallery()">🔄 Rafraîchir</button>
                <button class="danger" onclick="clearQueueFromGallery()" id="clearQueueGalleryBtn" style="display:none;">🗑 Vider la file</button>
            </div>
        </div>
        <div id="galleryContainer"><div class="gallery-empty">Chargement...</div></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script src="javascript.js?v=<?= (int)$JS_VER ?>" defer></script>
</body>
</html>