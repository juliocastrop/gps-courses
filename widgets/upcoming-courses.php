<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;
use GPSC\Course_Catalog;
use GPSC\Credits;
use GPSC\Home_Sections;

/**
 * GPS Upcoming Courses: section header + the /courses/ archive's course cards
 * (Course_Catalog::render_card(), so both stay identical; editions never list).
 * Empty slots are filled with a "More dates coming" card and a CE credits card.
 */
class Upcoming_Courses_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-upcoming-courses';
    }

    public function get_title() {
        return __('GPS Upcoming Courses', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_keywords() {
        return ['gps', 'courses', 'upcoming', 'catalog', 'cards', 'home'];
    }

    public function get_style_depends() {
        return ['gps-catalog', 'gps-home'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_head', [
            'label' => __('Heading', 'gps-courses'),
        ]);
        $this->add_control('eyebrow', [
            'label'       => __('Eyebrow', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Hands-on courses · Duluth, GA', 'gps-courses'),
        ]);
        $this->add_control('title', [
            'label'       => __('Title', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Upcoming courses', 'gps-courses'),
        ]);
        $this->add_control('title_tag', [
            'label'   => __('Title tag', 'gps-courses'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h2',
            'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p'],
        ]);
        $this->add_control('link_label', [
            'label'   => __('Link label', 'gps-courses'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('All courses →', 'gps-courses'),
        ]);
        $this->add_control('link', [
            'label'       => __('Link', 'gps-courses'),
            'type'        => Controls_Manager::URL,
            'placeholder' => '/courses/',
            'description' => __('Empty = the course catalog (/courses/).', 'gps-courses'),
        ]);
        $this->end_controls_section();

        $this->start_controls_section('section_cards', [
            'label' => __('Cards', 'gps-courses'),
        ]);
        $this->add_control('max_cards', [
            'label'   => __('Cards', 'gps-courses'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 12,
            'step'    => 1,
            'default' => 3,
            'description' => __('Upcoming courses first (soonest first); empty slots take the cards below.', 'gps-courses'),
        ]);

        $this->add_control('show_more', [
            'label'        => __('"More dates coming" card', 'gps-courses'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'separator'    => 'before',
        ]);
        $more = ['show_more' => 'yes'];
        $this->add_control('more_eyebrow', [
            'label'     => __('Eyebrow', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('More dates coming', 'gps-courses'),
            'condition' => $more,
        ]);
        $this->add_control('more_fallback', [
            'label'       => __('Title', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Past courses run again', 'gps-courses'),
            'description' => __('Below it, the card lists up to 3 past courses (photo, last dates, CE) linked to their pages, where visitors join the interest list.', 'gps-courses'),
            'condition'   => $more,
        ]);
        $this->add_control('more_text', [
            'label'     => __('Text', 'gps-courses'),
            'type'      => Controls_Manager::TEXTAREA,
            'rows'      => 3,
            'default'   => __('Join the interest list on any past course and we email you when new dates open.', 'gps-courses'),
            'condition' => $more,
        ]);
        $this->add_control('more_link_label', [
            'label'     => __('Link label', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('See past courses →', 'gps-courses'),
            'condition' => $more,
        ]);

        $this->add_control('show_ce', [
            'label'        => __('CE credits card', 'gps-courses'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'separator'    => 'before',
        ]);
        $ce = ['show_ce' => 'yes'];
        $this->add_control('ce_eyebrow', [
            'label'     => __('Eyebrow', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('CE credits', 'gps-courses'),
            'condition' => $ce,
        ]);
        $this->add_control('ce_title', [
            'label'       => __('Title', 'gps-courses'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => __('Nationally Approved PACE Program Provider', 'gps-courses'),
            'condition'   => $ce,
        ]);
        $this->add_control('ce_text', [
            'label'     => __('Text', 'gps-courses'),
            'type'      => Controls_Manager::TEXTAREA,
            'rows'      => 3,
            'default'   => __('Every course and seminar awards CE credits for FAGD/MAGD. Your credits and certificates are always in your account.', 'gps-courses'),
            'condition' => $ce,
        ]);
        $this->add_control('ce_link_label', [
            'label'     => __('Link label', 'gps-courses'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('My CE credits →', 'gps-courses'),
            'condition' => $ce,
        ]);
        $this->end_controls_section();
    }

    protected function render() {
        $s       = $this->get_settings_for_display();
        $max     = max(1, min(12, (int) ($s['max_cards'] ?? 3)));
        $courses = Course_Catalog::courses('upcoming', $max);
        $link    = Home_Sections::link_value($s['link'] ?? '');
        $catalog = Home_Sections::catalog_url();

        $fillers = [];
        $free    = $max - count($courses);
        if ($free > 0 && 'yes' === ($s['show_more'] ?? 'yes')) {
            $fillers[] = 'more';
            $free--;
        }
        if ($free > 0 && 'yes' === ($s['show_ce'] ?? 'yes')) {
            $fillers[] = 'ce';
        }

        echo '<section class="gps-home gps-home-courses">';
        echo Home_Sections::head([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Home_Sections::head()
            'eyebrow'    => $s['eyebrow'] ?? '',
            'title'      => $s['title'] ?? '',
            'tag'        => $s['title_tag'] ?? 'h2',
            'link_label' => $s['link_label'] ?? '',
            'link_url'   => '' !== $link['url'] ? $link['url'] : $catalog,
            'external'   => $link['external'],
            'nofollow'   => $link['nofollow'],
        ]);
        if (!$courses && !$fillers) {
            echo '</section>';
            return;
        }
        echo '<div class="gps-home-grid">';
        foreach ($courses as $i => $c) {
            Course_Catalog::render_card($c, $i);
        }
        foreach ($fillers as $kind) {
            if ('more' === $kind) {
                $past = Home_Sections::past_courses(3);
                $this->render_filler('more', [
                    'eyebrow' => $s['more_eyebrow'] ?? '',
                    // With the list of past courses below, the title stays short
                    'title'   => $past ? (string) ($s['more_fallback'] ?? '') : Home_Sections::runs_again([], (string) ($s['more_fallback'] ?? '')),
                    'text'    => $s['more_text'] ?? '',
                    'label'   => $s['more_link_label'] ?? '',
                    'url'     => $catalog . '#gps-past-h',
                    'past'    => $past,
                ]);
            } else {
                $this->render_filler('ce', [
                    'logo'    => Home_Sections::pace_logo_url(),
                    'eyebrow' => $s['ce_eyebrow'] ?? '',
                    'title'   => $s['ce_title'] ?? '',
                    'text'    => $s['ce_text'] ?? '',
                    'label'   => $s['ce_link_label'] ?? '',
                    'url'     => function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('gps-ce-credits') : home_url('/my-account/gps-ce-credits/'),
                ]);
            }
        }
        echo '</div></section>';
    }

    protected function render_filler($kind, array $f) {
        $eyebrow = trim((string) $f['eyebrow']);
        $title   = trim((string) $f['title']);
        $text    = trim((string) $f['text']);
        $label   = trim((string) $f['label']);
        ?>
        <article class="gps-home-fill gps-home-fill--<?php echo esc_attr($kind); ?>">
            <?php if ('more' === $kind) :
                $imgs = array_values(array_filter(array_column(isset($f['past']) ? (array) $f['past'] : [], 'image'))); ?>
                <div class="gps-home-fill__media gps-home-fill__media--mosaic" aria-hidden="true">
                    <?php foreach (array_slice($imgs, 0, 3) as $img) : ?>
                        <span class="gps-home-fill__tile" style="background-image:url('<?php echo esc_url($img); ?>')"></span>
                    <?php endforeach; ?>
                    <span class="gps-home-fill__badge"><?php esc_html_e('Past courses', 'gps-courses'); ?></span>
                </div>
            <?php else : ?>
                <div class="gps-home-fill__media gps-home-fill__media--ce">
                    <?php if (!empty($f['logo'])) : ?>
                        <img class="gps-home-fill__logo" src="<?php echo esc_url($f['logo']); ?>" alt="<?php esc_attr_e('PACE Program Approval for Continuing Education', 'gps-courses'); ?>" loading="lazy">
                    <?php endif; ?>
                    <span class="gps-home-fill__badge gps-home-fill__badge--gold"><?php esc_html_e('FAGD / MAGD credit', 'gps-courses'); ?></span>
                </div>
            <?php endif; ?>
            <div class="gps-home-fill__body">
                <?php if ('' !== $eyebrow) : ?><p class="gps-home-eyebrow"><?php echo esc_html($eyebrow); ?></p><?php endif; ?>
                <?php if ('' !== $title) : ?><h3 class="gps-home-fill__title"><?php echo esc_html($title); ?></h3><?php endif; ?>
                <?php if (!empty($f['past'])) : ?>
                    <ul class="gps-home-fill__list">
                        <?php foreach ($f['past'] as $p) : ?>
                            <li><a href="<?php echo esc_url($p['url']); ?>"><strong><?php echo esc_html($p['title']); ?></strong><span><?php
                                $meta = array_filter([$p['when'], $p['ce'] > 0 ? Credits::format($p['ce']) . ' CE' : '']);
                                echo esc_html(implode(' · ', $meta));
                            ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ('' !== $text) : ?><p class="gps-home-fill__text"><?php echo esc_html($text); ?></p><?php endif; ?>
                <?php if ('' !== $label && '' !== $f['url']) : ?><a class="gps-home-fill__link" href="<?php echo esc_url($f['url']); ?>"><?php echo esc_html($label); ?></a><?php endif; ?>
            </div>
        </article>
        <?php
    }
}
