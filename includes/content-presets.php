<?php
/**
 * Generic starter content presets, keyed by school type. {school} is
 * substituted with the real school name wherever it appears. Used by
 * get-started.php (self-serve signup) and admin/school-edit.php (re-seed tool).
 * Only fields matching a section's actual schema get applied — safe against
 * schema differences between themes.
 */
function get_school_content_presets(): array {
    return [
        'primary_day' => [
            'label' => 'Primary Day School',
            'content' => [
                'hero' => [
                    'headline' => 'Welcome to {school}',
                    'subheading' => 'Nurturing every learner to reach their full potential, academically, socially, and morally.',
                    'cta_text' => 'Get in Touch',
                    'cta_link' => '#contact',
                ],
                'about' => [
                    'body' => "{school} is committed to providing quality education in a safe, supportive environment.\n\nWe believe every child deserves individual attention and a strong foundation for the future.",
                ],
                'academics' => [
                    'body' => "Our curriculum covers all core subjects with a focus on strong foundational skills in literacy, numeracy, and life skills.\n\nSmall class sizes allow our teachers to give every learner the attention they need.",
                ],
                'admissions' => [
                    'body' => "Admissions are open throughout the year, subject to space availability.\n\nContact the school office to arrange a visit and learn more about enrolling your child.",
                ],
            ],
        ],
        'secondary' => [
            'label' => 'Secondary School',
            'content' => [
                'hero' => [
                    'headline' => 'Welcome to {school}',
                    'subheading' => 'Preparing students for academic excellence and responsible citizenship.',
                    'cta_text' => 'Apply Now',
                    'cta_link' => '#admissions',
                ],
                'about' => [
                    'body' => "{school} provides a rigorous secondary education that prepares students for national examinations and beyond.\n\nWe combine strong academics with discipline, character development, and co-curricular opportunities.",
                ],
                'academics' => [
                    'body' => "We offer the full national curriculum, taught by qualified and experienced teachers.\n\nStudents are supported through structured revision programs, regular assessments, and career guidance.",
                ],
                'admissions' => [
                    'body' => "We welcome applications from students transitioning from primary school as well as transfers.\n\nContact the school office for entry requirements and available slots.",
                ],
            ],
        ],
        'ecd_nursery' => [
            'label' => 'ECD / Nursery',
            'content' => [
                'hero' => [
                    'headline' => 'Welcome to {school}',
                    'subheading' => 'A warm, caring start to your child\'s learning journey.',
                    'cta_text' => 'Book a Visit',
                    'cta_link' => '#contact',
                ],
                'about' => [
                    'body' => "At {school}, we provide a safe and nurturing environment where young children can play, explore, and begin learning.\n\nOur programs are designed around each child's developmental stage.",
                ],
                'academics' => [
                    'body' => "Our early learning program builds foundational skills through play-based activities, storytelling, songs, and guided early literacy and numeracy.",
                ],
                'admissions' => [
                    'body' => "We accept children on a rolling basis throughout the year. Contact us to arrange a visit and discuss enrollment.",
                ],
            ],
        ],
        'boarding' => [
            'label' => 'Boarding School',
            'content' => [
                'hero' => [
                    'headline' => 'Welcome to {school}',
                    'subheading' => 'A home away from home, built on discipline, community, and academic excellence.',
                    'cta_text' => 'Apply Now',
                    'cta_link' => '#admissions',
                ],
                'about' => [
                    'body' => "{school} offers a structured boarding environment where students grow academically, socially, and personally under close mentorship.\n\nOur staff are committed to the wellbeing and success of every student in our care.",
                ],
                'academics' => [
                    'body' => "Our academic program follows the national curriculum with structured study time, regular assessments, and dedicated exam preparation for boarders.",
                ],
                'admissions' => [
                    'body' => "Boarding places are limited and offered on a first-come basis. Contact the school office for entry requirements, fees, and available slots.",
                ],
            ],
        ],
        'mixed_day_boarding' => [
            'label' => 'Mixed Day & Boarding',
            'content' => [
                'hero' => [
                    'headline' => 'Welcome to {school}',
                    'subheading' => 'Flexible day and boarding options, one standard of excellence.',
                    'cta_text' => 'Learn More',
                    'cta_link' => '#admissions',
                ],
                'about' => [
                    'body' => "{school} offers both day and boarding options, giving families the flexibility to choose what works best for them without compromising on quality.\n\nAll students, day or boarding, receive the same high standard of teaching and care.",
                ],
                'academics' => [
                    'body' => "We follow the national curriculum with qualified teaching staff, regular assessments, and additional support for students preparing for national exams.",
                ],
                'admissions' => [
                    'body' => "We welcome applications for both day and boarding places. Contact the school office to discuss options, fees, and availability.",
                ],
            ],
        ],
        'blank' => [
            'label' => 'Blank (no generic content)',
            'content' => [],
        ],
    ];
}
