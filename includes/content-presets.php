<?php
/**
 * Generic starter content presets, keyed by school type. {school} is
 * substituted with the real school name wherever it appears. Used by
 * get-started.php (self-serve signup) and admin/school-edit.php (re-seed tool).
 * Only fields matching a section's actual schema get applied — safe against
 * schema differences between templates.
 *
 * Each school type's copy (including FAQ) is written for that type
 * specifically — a boarding school's FAQ talks about visiting days and
 * what to pack; a day school's talks about pickup/drop-off; ECD's talks
 * about settling-in and daily routine. Nothing here is shared verbatim
 * across types.
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
                    'body' => "Admissions are open throughout the year, subject to space availability.\n\nWe ask that a parent or guardian bring the child's birth certificate, immunization record, and previous school report (if transferring) for the enrollment interview.",
                ],
                'faq' => [
                    'question_1' => 'What time does the school day start and end?',
                    'answer_1' => 'Gates open at 7:00am for early drop-off, with lessons running until 3:30pm. Contact the office for the exact daily timetable by grade.',
                    'question_2' => 'Do you provide transport or a school bus?',
                    'answer_2' => 'Ask the school office about current transport routes and whether your area is covered.',
                    'question_3' => 'What should my child bring on their first day?',
                    'answer_3' => 'A copy of their birth certificate, immunization card, two passport photos, and any items on the school\'s uniform and stationery list, available from the office.',
                    'question_4' => 'Can I transfer my child mid-term?',
                    'answer_4' => 'Yes, subject to space availability. Bring their most recent report form and a transfer letter from their previous school.',
                ],
                'stats' => [
                    'stat_1_number' => '98%', 'stat_1_label' => 'KCPE Transition Rate',
                    'stat_2_number' => '1:25', 'stat_2_label' => 'Teacher-to-Pupil Ratio',
                    'stat_3_number' => '15+', 'stat_3_label' => 'Years Serving Our Community',
                ],
                'cta_banner' => [
                    'headline' => 'Ready to enroll your child at {school}?',
                    'subtext' => 'Book a visit or speak to our office team about admissions.',
                    'button_text' => 'Apply for Admission',
                    'button_link' => '#admissions',
                ],
                'results_lookup' => ['intro_text' => "Check your child's end-of-term results using their admission number below."],
                'enrollment_form' => ['intro_text' => 'Start your child\'s application online — our office will follow up to confirm your visit and required documents.'],
                'fees' => ['intro_text' => 'Fees below are per term. Contact the office for the current bank/M-Pesa payment details and any sibling discount policy.'],
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
                    'body' => "We offer the full national curriculum, taught by qualified and experienced teachers.\n\nStudents are supported through structured revision programs, regular assessments, and career guidance ahead of national examinations.",
                ],
                'admissions' => [
                    'body' => "We welcome applications from students transitioning from primary school as well as transfers from other secondary schools.\n\nApplicants should bring their KCPE result slip (or most recent report form for transfers) and a transfer letter where applicable.",
                ],
                'faq' => [
                    'question_1' => 'What KCPE grade is required for admission?',
                    'answer_1' => 'Contact the school office for the current minimum entry grade, which may vary by year and available slots.',
                    'question_2' => 'Do you offer both day and boarding options?',
                    'answer_2' => 'Ask the office about which options are currently available at this school.',
                    'question_3' => 'What subjects and clubs do you offer?',
                    'answer_3' => 'See our Academics section above, or contact the office for the full subject and co-curricular activity list.',
                    'question_4' => 'When are visiting/parents\' days?',
                    'answer_4' => 'Visiting day dates are shared each term — contact the office or check with your child\'s class teacher.',
                ],
                'stats' => [
                    'stat_1_number' => 'B+', 'stat_1_label' => 'Mean KCSE Grade',
                    'stat_2_number' => '95%', 'stat_2_label' => 'University Placement Rate',
                    'stat_3_number' => '1:30', 'stat_3_label' => 'Teacher-to-Student Ratio',
                ],
                'cta_banner' => [
                    'headline' => 'Join {school} this intake',
                    'subtext' => 'Applications are reviewed on a rolling basis — apply early to secure a slot.',
                    'button_text' => 'Apply Now',
                    'button_link' => '#admissions',
                ],
                'results_lookup' => ['intro_text' => "Check your child's term results and exam performance using their admission number below."],
                'enrollment_form' => ['intro_text' => 'Apply online and our admissions office will contact you to confirm documents and next steps.'],
                'fees' => ['intro_text' => 'Fees below are per term. Contact the bursar\'s office for the current payment schedule and bank details.'],
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
                    'body' => "At {school}, we provide a safe and nurturing environment where young children can play, explore, and begin learning.\n\nOur programs are designed around each child's developmental stage, with warm, attentive caregivers throughout the day.",
                ],
                'academics' => [
                    'body' => "Our early learning program builds foundational skills through play-based activities, storytelling, songs, and guided early literacy and numeracy — preparing children for a confident transition into Grade 1.",
                ],
                'admissions' => [
                    'body' => "We accept children on a rolling basis throughout the year, from age 2 upwards. Contact us to arrange a visit, meet the caregivers, and discuss your child's settling-in plan.",
                ],
                'faq' => [
                    'question_1' => 'What age groups do you take?',
                    'answer_1' => 'Contact the office for the specific age bands and class groupings currently offered.',
                    'question_2' => 'How do you help my child settle in?',
                    'answer_2' => 'We offer a gradual settling-in period so your child can get comfortable with their caregiver and classmates before full days begin.',
                    'question_3' => 'What should I pack daily?',
                    'answer_3' => 'A change of clothes, any comfort item, and a labeled water bottle. Meal arrangements are confirmed at enrollment.',
                    'question_4' => 'Do you provide meals or snacks?',
                    'answer_4' => 'Ask the office about current meal/snack arrangements and any allergy accommodation process.',
                ],
                'stats' => [
                    'stat_1_number' => '1:12', 'stat_1_label' => 'Caregiver-to-Child Ratio',
                    'stat_2_number' => '100%', 'stat_2_label' => 'Trained Early-Years Staff',
                ],
                'cta_banner' => [
                    'headline' => 'Give your child a warm start at {school}',
                    'subtext' => 'Book a visit to see our classrooms and meet our caregivers.',
                    'button_text' => 'Book a Visit',
                    'button_link' => '#contact',
                ],
                'enrollment_form' => ['intro_text' => 'Start your child\'s application online — we\'ll follow up to arrange a visit and settling-in plan.'],
                'fees' => ['intro_text' => 'Fees below are per term and include standard daily activities. Contact the office about meal plans and any additional costs.'],
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
                    'body' => "{school} offers a structured boarding environment where students grow academically, socially, and personally under close mentorship.\n\nOur staff are committed to the wellbeing and success of every student in our care, day and night.",
                ],
                'academics' => [
                    'body' => "Our academic program follows the national curriculum with structured study time, regular assessments, and dedicated exam preparation for boarders — including supervised evening prep.",
                ],
                'admissions' => [
                    'body' => "Boarding places are limited and offered on a first-come basis. Applicants should bring their most recent report form, a transfer letter (if applicable), and a medical form completed by a licensed practitioner.",
                ],
                'faq' => [
                    'question_1' => 'What items should my child bring to the dormitory?',
                    'answer_1' => 'A full boarding kit list (bedding, uniform, toiletries, trunk) is issued at admission — contact the office for the current list.',
                    'question_2' => 'When are visiting days and half-terms?',
                    'answer_2' => 'Visiting day and half-term dates are published each term in the school calendar — contact the office for current dates.',
                    'question_3' => 'How do you handle student welfare and discipline?',
                    'answer_3' => 'Each dormitory has a matron/patron and house staff who oversee student welfare, with a school counselor available for additional support.',
                    'question_4' => 'Can boarders go home on weekends?',
                    'answer_4' => 'Weekend leave policies vary by grade and term — ask the office about the current exeat/leave policy.',
                ],
                'stats' => [
                    'stat_1_number' => '24/7', 'stat_1_label' => 'Boarding Supervision',
                    'stat_2_number' => 'A-', 'stat_2_label' => 'Mean KCSE Grade',
                    'stat_3_number' => '90%+', 'stat_3_label' => 'University Placement Rate',
                ],
                'cta_banner' => [
                    'headline' => 'Secure your child\'s place at {school}',
                    'subtext' => 'Boarding slots are limited each intake — apply early.',
                    'button_text' => 'Apply Now',
                    'button_link' => '#admissions',
                ],
                'results_lookup' => ['intro_text' => "Parents can check their child's term results using their admission number below."],
                'enrollment_form' => ['intro_text' => 'Apply online and our admissions office will contact you to confirm boarding slot availability and required documents.'],
                'fees' => ['intro_text' => 'Fees below cover tuition and boarding per term. Contact the bursar\'s office for the boarding kit list and payment schedule.'],
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
                    'body' => "We follow the national curriculum with qualified teaching staff, regular assessments, and additional support for students preparing for national exams — with evening study support available for boarders.",
                ],
                'admissions' => [
                    'body' => "We welcome applications for both day and boarding places. Applicants should indicate their preferred option and bring their most recent report form and transfer letter where applicable.",
                ],
                'faq' => [
                    'question_1' => 'Can I switch my child from day to boarding later?',
                    'answer_1' => 'Yes, subject to space availability — speak to the office about switching options between terms.',
                    'question_2' => 'Do day and boarding students follow the same timetable?',
                    'answer_2' => 'Yes, all students follow the same academic timetable; boarders have additional supervised evening prep.',
                    'question_3' => 'What transport options are available for day scholars?',
                    'answer_3' => 'Ask the school office about current transport routes and whether your area is covered.',
                    'question_4' => 'What should a new boarding student bring?',
                    'answer_4' => 'A full boarding kit list is issued at admission for boarding applicants — contact the office for the current list.',
                ],
                'stats' => [
                    'stat_1_number' => '2', 'stat_1_label' => 'Day & Boarding Options',
                    'stat_2_number' => 'B', 'stat_2_label' => 'Mean KCSE Grade',
                    'stat_3_number' => '1:30', 'stat_3_label' => 'Teacher-to-Student Ratio',
                ],
                'cta_banner' => [
                    'headline' => 'Find the right fit at {school}',
                    'subtext' => 'Day or boarding — talk to us about which option suits your family.',
                    'button_text' => 'Learn More',
                    'button_link' => '#admissions',
                ],
                'results_lookup' => ['intro_text' => "Check your child's term results using their admission number below."],
                'enrollment_form' => ['intro_text' => 'Apply online for either day or boarding — our office will follow up on availability and required documents.'],
                'fees' => ['intro_text' => 'Fees below vary by day/boarding option and are shown per term. Contact the bursar\'s office for the current schedule.'],
            ],
        ],
        'blank' => [
            'label' => 'Blank (no generic content)',
            'content' => [],
        ],
    ];
}
