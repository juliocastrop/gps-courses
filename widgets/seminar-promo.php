<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use GPSC\Course_Page;
use GPSC\Credits;
use GPSC\Home_Sections;
use GPSC\Seminar_Page;

/**
 * GPS Seminar Promo: navy card for the monthly seminars program. Left: copy
 * and buttons to the seminar product page. Right: the next session (from the
 * seminar's sessions) and program stats (sessions, CE per cycle, enrollment price).
 */
class Seminar_Promo_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-seminar-promo';
    }

    public function get_title() {
        return __('GPS Seminar Promo', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-call-to-action';
    }

    public function get_keywords() {
        return ['gps', 'seminar', 'monthly', 'study club', 'promo', 'home'];
    }

    public function get_style_depends() {
        return ['gps-home'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'gps-courses'),
        ]);

        $this->add_control('eyebrow', [
            'label'       => __('Eyebrow', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Monthly seminars · GPS Study Club', 'gps-courses'),
        ]);
        $this->add_control('title', [
            'label'       => __('Title', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Join the Monthly Seminars', 'gps-courses'),
        ]);
        $this->add_control('title_tag', [
            'label'   => __('Title tag', 'gps-courses'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p'],
        ]);
        $this->add_control('text', [
            'label'   => __('Text', 'gps-courses'),
            'type'    => Controls_Manager::TEXTAREA,
            'rows'    => 3,
            'default' => __('A 10-session cycle of literature review, case discussions and treatment planning, with 2 CE credits per session.', 'gps-courses'),
        ]);
        $this->add_control('program_label', [
            'label'     => __('Program button', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('See the program', 'gps-courses'),
            'separator' => 'before',
        ]);
        $this->add_control('register_label', [
            'label'   => __('Register button', 'gps-courses'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Register', 'gps-courses'),
        ]);
        $this->add_control('register_anchor', [
            'label'       => __('Register anchor', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'registration',
            'description' => __('Section id on the seminar page the Register button jumps to (the enroll box is "registration"). Empty = the page top.', 'gps-courses'),
        ]);
        $this->add_control('links_note', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => esc_html__('Both buttons go to the current seminar product page. The next session, sessions, CE credits and price come from the seminar and its WooCommerce product.', 'gps-courses'),
            'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('section_side', [
            'label' => __('Next session & stats', 'gps-courses'),
        ]);
        $this->add_control('show_session', [
            'label'        => __('Show next session', 'gps-courses'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);
        $this->add_control('next_label', [
            'label'     => __('Next session label', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Next session', 'gps-courses'),
            'condition' => ['show_session' => 'yes'],
        ]);
        $this->add_control('location', [
            'label'       => __('Location', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => 'Duluth, GA',
            'description' => __('Empty = the city from Seminar page settings.', 'gps-courses'),
            'condition'   => ['show_session' => 'yes'],
        ]);
        $this->add_control('show_stats', [
            'label'        => __('Show stats', 'gps-courses'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'separator'    => 'before',
        ]);
        $this->add_control('sessions_label', [
            'label'     => __('Sessions label', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Sessions', 'gps-courses'),
            'condition' => ['show_stats' => 'yes'],
        ]);
        $this->add_control('ce_label', [
            'label'     => __('CE label', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('CE per cycle', 'gps-courses'),
            'condition' => ['show_stats' => 'yes'],
        ]);
        $this->add_control('show_price', [
            'label'        => __('Show price', 'gps-courses'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => ['show_stats' => 'yes'],
        ]);
        $this->add_control('price_label', [
            'label'     => __('Price label', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Enrollment', 'gps-courses'),
            'condition' => ['show_stats' => 'yes', 'show_price' => 'yes'],
        ]);
        $this->end_controls_section();
    }

    protected function render() {
        $s    = $this->get_settings_for_display();
        $data = Home_Sections::seminar_promo();
        $tag  = Home_Sections::tag($s['title_tag'] ?? 'h2');

        $url = $data ? $data['url'] : '';
        if ('' === $url) {
            $url = home_url('/product/gps-monthly-seminars/');
        }
        $anchor   = sanitize_title((string) ($s['register_anchor'] ?? ''));
        $register = $anchor ? $url . '#' . $anchor : $url;

        $sessions = $data ? $data['sessions'] : Seminar_Page::PROGRAM_SESSIONS;
        $ce_total = $data ? $data['ce_total'] : Seminar_Page::PROGRAM_SESSIONS * Seminar_Page::CE_PER_SESSION;
        $ce_each  = $data ? $data['ce_each'] : Seminar_Page::CE_PER_SESSION;
        $next     = ($data && 'yes' === ($s['show_session'] ?? 'yes')) ? $data['next'] : null;
        $stats    = 'yes' === ($s['show_stats'] ?? 'yes');
        $price    = ($data && $stats && 'yes' === ($s['show_price'] ?? 'yes') && $data['price'] > 0) ? Course_Page::price($data['price']) : '';
        $place    = trim((string) ($s['location'] ?? '')) ?: ($data ? $data['city'] : '');
        $ce_fmt   = function ($n) { return class_exists('\\GPSC\\Credits') ? Credits::format($n) : (string) (0 + round((float) $n, 2)); };

        $eyebrow = trim((string) ($s['eyebrow'] ?? ''));
        $title   = trim((string) ($s['title'] ?? ''));
        $text    = trim((string) ($s['text'] ?? ''));
        $l_prog  = trim((string) ($s['program_label'] ?? ''));
        $l_reg   = trim((string) ($s['register_label'] ?? ''));
        $has_side = $next || $stats;
        if (!$has_side && '' === $eyebrow . $title . $text . $l_prog . $l_reg) {
            return; // everything switched off or empty: no empty card
        }
        ?>
        <section class="gps-home gps-home-sem">
            <div class="gps-home-sem__card<?php echo $has_side ? '' : ' gps-home-sem__card--solo'; ?>">
                <div class="gps-home-sem__main">
                    <?php if ('' !== $eyebrow) : ?><p class="gps-home-eyebrow gps-home-eyebrow--dark"><?php echo esc_html($eyebrow); ?></p><?php endif; ?>
                    <?php if ('' !== $title) : ?><<?php echo esc_html($tag); ?> class="gps-home-sem__title"><?php echo esc_html($title); ?></<?php echo esc_html($tag); ?>><?php endif; ?>
                    <?php if ('' !== $text) : ?><p class="gps-home-sem__text"><?php echo esc_html($text); ?></p><?php endif; ?>
                    <?php if ('' !== $l_prog || '' !== $l_reg) : ?>
                        <div class="gps-home-sem__btns">
                            <?php if ('' !== $l_prog) : ?><a class="gps-home-btn gps-home-btn--gold" href="<?php echo esc_url($url); ?>"><?php echo esc_html($l_prog); ?></a><?php endif; ?>
                            <?php if ('' !== $l_reg) : ?><a class="gps-home-btn gps-home-btn--outline" href="<?php echo esc_url($register); ?>"><?php echo esc_html($l_reg); ?></a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($has_side) : ?>
                    <div class="gps-home-sem__side">
                        <?php if ($next) : ?>
                            <div class="gps-home-sem__next">
                                <p class="gps-home-sem__label"><?php echo esc_html((string) ($s['next_label'] ?? '')); ?></p>
                                <div class="gps-home-sem__session">
                                    <span class="gps-home-date" aria-hidden="true"><span class="gps-home-date__mon"><?php echo esc_html($next['mon']); ?></span><span class="gps-home-date__day"><?php echo esc_html($next['day']); ?></span></span>
                                    <div class="gps-home-sem__info">
                                        <p class="gps-home-sem__topic"><?php
                                            /* translators: %d: session number */
                                            echo esc_html(implode(' · ', array_filter([sprintf(__('Session %d', 'gps-courses'), $next['number']), $next['topic']])));
                                        ?></p>
                                        <p class="gps-home-sem__meta"><?php
                                            /* translators: %s: CE credits */
                                            echo esc_html(implode(' · ', array_filter([trim($next['weekday'] . ' ' . $next['time']), $place, sprintf(__('%s CE', 'gps-courses'), $ce_fmt($ce_each))])));
                                        ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($stats) : ?>
                            <ul class="gps-home-stats">
                                <li><strong><?php echo esc_html((string) $sessions); ?></strong><span><?php echo esc_html((string) ($s['sessions_label'] ?? '')); ?></span></li>
                                <li><strong><?php echo esc_html($ce_fmt($ce_total)); ?></strong><span><?php echo esc_html((string) ($s['ce_label'] ?? '')); ?></span></li>
                                <?php if ('' !== $price) : ?>
                                    <li><strong><?php echo wp_kses_post($price); ?></strong><span><?php echo esc_html((string) ($s['price_label'] ?? '')); ?></span></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}
