<!DOCTYPE html>
<html lang="<?php echo $force_bilingual ? 'hu' : esc_attr( $active_lang ); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        if ( $force_bilingual ) echo 'Karbantartás alatt / Under Maintenance';
        else echo $active_lang === 'en' ? 'Under Maintenance' : 'Karbantartás alatt'; 
        ?>
    </title>
    <style>
        :root {
            --primary-color: #242943;
            --secondary-color: #50ADC9;
            --grey-color: #D2D2D2;
        }

        body {
            font-family: Tahoma, Geneva, sans-serif;
            background-color: #f8f9fa;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .cmm-container {
            background: #ffffff;
            max-width: 650px;
            width: 100%;
            padding: 50px 40px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(36, 41, 67, 0.08);
            text-align: center;
            border-top: 4px solid var(--secondary-color);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .cmm-logo-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .cmm-logo-img {
            max-width: 280px;
            height: auto;
        }

        .cmm-logo-svg svg {
            width: 100%;
            max-width: 280px;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        
        .cmm-logo-svg svg .cls-1 { fill: #1c2442; }
        .cmm-logo-svg svg .cls-2 { fill: #00adcb; }

        .cmm-content {
            width: 100%;
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 40px;
            color: var(--primary-color);
        }

        .cmm-content h1, 
        .cmm-content h2, 
        .cmm-content h3 {
            color: var(--primary-color);
            margin-top: 0;
        }
        
        hr.cmm-separator {
            border: 0;
            border-top: 1px solid var(--grey-color);
            margin: 30px 0;
            width: 100%;
        }

        .cmm-links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
            width: 100%;
        }

        .cmm-links a {
            display: block;
            width: 100%;
            max-width: 320px;
            box-sizing: border-box;
            padding: 14px 20px;
            background: var(--secondary-color);
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 1em;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .cmm-links a.cmm-home-btn {
            background: var(--primary-color);
        }

        .cmm-links a:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .cmm-links a.cmm-home-btn:hover {
            background: var(--secondary-color);
        }

        .cmm-footer {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--grey-color);
            font-size: 0.9em;
            line-height: 1.5;
            color: #555;
            width: 100%;
        }

        .cmm-footer strong {
            color: var(--primary-color);
        }

        .cmm-footer a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: bold;
        }

        .cmm-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="cmm-container">
        
        <div class="cmm-logo-wrapper">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="cmm-logo-img" />
            <?php elseif ( ! empty( $logo_svg ) ) : ?>
                <div class="cmm-logo-svg">
                    <?php echo $logo_svg; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="cmm-content">
            <?php if ( $force_bilingual ) : ?>
                <div class="cmm-lang-hu"><?php echo wp_kses_post( $text_hu ); ?></div>
                <hr class="cmm-separator">
                <div class="cmm-lang-en"><?php echo wp_kses_post( $text_en ); ?></div>
            <?php else : ?>
                <?php echo wp_kses_post( $active_lang === 'en' ? $text_en : $text_hu ); ?>
            <?php endif; ?>
        </div>

        <div class="cmm-links">
            <?php if ( $is_specific_mode && isset($home_btn_hu) && isset($home_btn_en) ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cmm-home-btn">
                    <?php echo esc_html( $force_bilingual ? $home_btn_hu . ' / ' . $home_btn_en : ($active_lang === 'en' ? $home_btn_en : $home_btn_hu) ); ?>
                </a>
            <?php endif; ?>

            <?php if ( ! empty( $links ) ) : ?>
                <?php foreach ( $links as $link ) : ?>
                    <?php 
                    if ( ! empty( $link['url'] ) ) : 
                        
                        $label_hu = isset( $link['label'] ) && trim($link['label']) !== '' ? trim($link['label']) : '';
                        $label_en = isset( $link['label_en'] ) && trim($link['label_en']) !== '' ? trim($link['label_en']) : '';
                        
                        
                        if ($label_hu === '' && $label_en !== '') $label_hu = $label_en;
                        if ($label_en === '' && $label_hu !== '') $label_en = $label_hu;

                        // Megjelenítendő szöveg összeállítása
                        $display_label = '';
                        if ( $force_bilingual ) {
                            if ( $label_hu !== '' && $label_en !== '' && $label_hu !== $label_en ) {
                                $display_label = $label_hu . ' / ' . $label_en;
                            } else {
                                $display_label = $label_hu !== '' ? $label_hu : $label_en;
                            }
                        } else {
                            $display_label = $active_lang === 'en' ? $label_en : $label_hu;
                        }

                    
                        if ( $display_label !== '' ) :
                    ?>
                        <a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $display_label ); ?></a>
                    <?php 
                        endif;
                    endif; 
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="cmm-footer">
            <?php if ( $force_bilingual ) : ?>
                <p><?php echo wp_kses_post( $footer_hu ); ?></p>
                <p><?php echo wp_kses_post( $footer_en ); ?></p>
            <?php else : ?>
                <p><?php echo wp_kses_post( $active_lang === 'en' ? $footer_en : $footer_hu ); ?></p>
            <?php endif; ?>
            <p><a href="mailto:website@sze.hu">website@sze.hu</a></p>
        </div>
    </div>
</body>
</html>