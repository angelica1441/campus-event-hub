<?php
// =============================================
// CAMPUS EVENT HUB - STUDENT CHATBOT BACKEND
// =============================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function ce_reply(string $reply, array $events = []): array {
    return [
        'reply' => $reply,
        'events' => $events
    ];
}

function ce_clean(string $text): string {
    $text = trim($text);

    return function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
}

function ce_has(string $text, array $keywords): bool {
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && strpos($text, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

// =============================================
// GET EVENTS FROM DATABASE
// =============================================

function ce_get_events(
    PDO $pdo,
    string $type = 'upcoming',
    int $limit = 6
): array {

    $today = date('Y-m-d');
    $limit = max(1, min($limit, 20));

    if ($type === 'past') {

        $sql = "
            SELECT id, title, description, category,
                   event_date, start_time, end_time,
                   venue, organizer, status
            FROM events
            WHERE event_date < :today
            AND LOWER(COALESCE(status, ''))
                NOT IN ('cancelled', 'canceled', 'deleted', 'archived')
            ORDER BY event_date DESC
            LIMIT $limit
        ";

    } else {

        $sql = "
            SELECT id, title, description, category,
                   event_date, start_time, end_time,
                   venue, organizer, status
            FROM events
            WHERE event_date >= :today
            AND LOWER(COALESCE(status, ''))
                IN ('upcoming', 'ongoing', 'active')
            ORDER BY event_date ASC, start_time ASC
            LIMIT $limit
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['today' => $today]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =============================================
// FORMAT EVENT CARDS
// =============================================

function ce_event_response(array $events): array {

    $cards = [];

    foreach ($events as $event) {

        $date = $event['event_date'] ?? '';

        if ($date && strtotime($date)) {
            $date = date('M j, Y', strtotime($date));
        }

        $start = trim((string)($event['start_time'] ?? ''));
        $end = trim((string)($event['end_time'] ?? ''));

        if ($start && strtotime($start)) {
            $start = date('g:i A', strtotime($start));
        }

        if ($end && strtotime($end)) {
            $end = date('g:i A', strtotime($end));
        }

        $cards[] = [
            'id' => (int)($event['id'] ?? 0),
            'title' => $event['title'] ?? 'Untitled event',
            'description' => $event['description'] ?? '',
            'category' => $event['category'] ?? 'General',
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'venue' => $event['venue'] ?? 'To be announced',
            'organizer' => $event['organizer'] ?? 'Campus Event Hub'
        ];
    }

    return $cards;
}

// =============================================
// GET EVENT CATEGORIES
// =============================================

function ce_categories(PDO $pdo): array {

    $stmt = $pdo->query("
        SELECT DISTINCT category
        FROM events
        WHERE category IS NOT NULL
        AND TRIM(category) <> ''
        AND LOWER(COALESCE(status, ''))
            IN ('upcoming', 'ongoing', 'active')
        ORDER BY category ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// =============================================
// CHATBOT RESPONSE SYSTEM
// =============================================

function chatbot_reply(string $message, PDO $pdo): array {

    $message = ce_clean($message);

    if ($message === '') {
        return ce_reply(
            "Hello! I'm your Campus Event Hub Assistant. "
            . "You can ask me about campus events, schedules, "
            . "venues, registration, and website navigation."
        );
    }

    // GREETINGS
    if (preg_match(
        '/^(hi|hello|hey|good morning|good afternoon|good evening|kumusta|kamusta|hello there)[!. ]*$/u',
        $message
    )) {
        return ce_reply(
            "Hello! Welcome to Campus Event Hub!\n\n"
            . "I'm here to help you find campus activities "
            . "and understand how to use the website.\n\n"
            . "You can ask me about:\n"
            . "• Upcoming events\n"
            . "• Event schedules and venues\n"
            . "• Event categories\n"
            . "• Event organizers\n"
            . "• Registration procedures\n"
            . "• Account assistance\n\n"
            . "What would you like to know?"
        );
    }

    // THANK YOU
    if (ce_has($message, [
        'thank you', 'thanks', 'thank u',
        'salamat', 'thanks a lot'
    ])) {
        return ce_reply(
            "You're welcome! I'm happy to help.\n\n"
            . "Feel free to ask me another question "
            . "about Campus Event Hub anytime."
        );
    }

    // CHATBOT IDENTITY
    if (ce_has($message, [
        'who are you', 'your name',
        'what are you', 'who made you'
    ])) {
        return ce_reply(
            "I'm the Campus Event Hub Assistant, "
            . "a student-friendly virtual assistant designed "
            . "to help students explore campus events, "
            . "find event information, and navigate the website."
        );
    }

    // HELP
    if (ce_has($message, [
        'help', 'what can you do', 'features',
        'tulong', 'what can i ask', 'what do you do'
    ])) {
        return ce_reply(
            "Here are the things I can help you with:\n\n"
            . "1. Upcoming and past campus events\n"
            . "2. Event categories and descriptions\n"
            . "3. Event schedules and dates\n"
            . "4. Event venues and locations\n"
            . "5. Event organizers\n"
            . "6. Event registration instructions\n"
            . "7. Registration cancellation guidance\n"
            . "8. My Registrations guidance\n"
            . "9. Login and account assistance\n"
            . "10. Website navigation\n\n"
            . "You can type your question naturally "
            . "or select one of the suggested questions."
        );
    }

    // CANCELLATION
    if (ce_has($message, [
        'cancel registration',
        'cancel my registration',
        'unregister',
        'remove my registration',
        'cancel my booking',
        'cancel event registration',
        'mag cancel',
        'kanselahin ang registration'
    ])) {
        return ce_reply(
            "To check whether you can cancel your registration:\n\n"
            . "1. Sign in to your Campus Event Hub account.\n"
            . "2. Open My Registrations, if available.\n"
            . "3. Find the event you registered for.\n"
            . "4. Check whether a Cancel option is available.\n"
            . "5. Follow the instructions shown on the website.\n\n"
            . "If there is no cancellation option, "
            . "please contact the event organizer or administrator."
        );
    }

    // MY REGISTRATIONS
    if (ce_has($message, [
        'my registration',
        'my registrations',
        'registered events',
        'events i joined',
        'events i registered',
        'check my registration',
        'registration status',
        'nakaregister'
    ])) {
        return ce_reply(
            "To check your registered events:\n\n"
            . "1. Sign in to your account.\n"
            . "2. Open My Registrations from the website menu "
            . "or your account menu, if available.\n"
            . "3. Review the events listed on the page.\n\n"
            . "Make sure you are using the correct account. "
            . "If an event is missing, contact its organizer "
            . "or the website administrator."
        );
    }

    // REGISTRATION
    if (ce_has($message, [
        'register',
        'registration',
        'join event',
        'sign up',
        'how to join',
        'paano sumali',
        'mag register',
        'mag-register',
        'how do i join',
        'how can i participate',
        'sumali sa event'
    ])) {
        return ce_reply(
            "Here's how to register for a campus event:\n\n"
            . "1. Open the Events page.\n"
            . "2. Choose the event you want to attend.\n"
            . "3. Read the event details carefully.\n"
            . "4. Look for the Register or Join button.\n"
            . "5. Sign in if the website requires it.\n"
            . "6. Follow the registration instructions.\n\n"
            . "You may check My Registrations afterward "
            . "if that feature is available.\n\n"
            . "If there is no Register button, online registration "
            . "may not be available for that event."
        );
    }

    // LOGIN AND ACCOUNT
    if (ce_has($message, [
        'login',
        'log in',
        'sign in',
        'cannot access account',
        'cant access account',
        'forgot password',
        'password',
        'create account',
        'register account',
        'sign up account',
        'account help'
    ])) {
        return ce_reply(
            "Here's some guidance for your account:\n\n"
            . "LOGIN:\n"
            . "Open the Login page and enter your registered details.\n\n"
            . "CREATE ACCOUNT:\n"
            . "Open Create Account or Register and complete "
            . "the required information.\n\n"
            . "FORGOT PASSWORD:\n"
            . "Use the password recovery option if the website "
            . "provides one. Otherwise, contact the administrator.\n\n"
            . "For your security, never share your password "
            . "in this chat."
        );
    }

    // WEBSITE SUPPORT
    if (ce_has($message, [
        'contact',
        'support',
        'administrator',
        'admin',
        'report a problem',
        'website problem',
        'bug',
        'error'
    ])) {
        return ce_reply(
            "If you encounter a website issue, please:\n\n"
            . "1. Take note of the page where the issue happened.\n"
            . "2. Describe what you were trying to do.\n"
            . "3. Contact the Campus Event Hub administrator "
            . "or the event organizer.\n\n"
            . "Please do not share your password "
            . "or sensitive account information in this chat."
        );
    }

    // PAST EVENTS
    if (ce_has($message, [
        'past event',
        'previous event',
        'old event',
        'completed event',
        'last event',
        'ended event',
        'event history',
        'naganap na'
    ])) {
        $events = ce_get_events($pdo, 'past');

        if (!$events) {
            return ce_reply(
                "I couldn't find past events in the current records. "
                . "Please check the Events page or contact the administrator."
            );
        }

        return ce_reply(
            "Here are the past events recorded in Campus Event Hub:",
            ce_event_response($events)
        );
    }

    // EVENT CATEGORIES
    if (ce_has($message, [
        'category',
        'categories',
        'type of event',
        'types of event',
        'kinds of event',
        'event types',
        'anong event category',
        'event categories',
        'mga kategorya'
    ])) {
        $categories = ce_categories($pdo);

        if (!$categories) {
            return ce_reply(
                "There are no event categories available "
                . "in the current records. Please check again later."
            );
        }

        return ce_reply(
            "These are the event categories currently listed:\n\n• "
            . implode("\n• ", $categories)
        );
    }

    // EVENT VENUES
    if (ce_has($message, [
        'venue',
        'location',
        'where',
        'place',
        'saan',
        'held',
        'gaganapin',
        'lokasyon'
    ])) {
        $events = ce_get_events($pdo, 'upcoming');

        if (!$events) {
            return ce_reply(
                "There are no upcoming events with venue information right now."
            );
        }

        $reply = "Here are the venues listed for upcoming events:\n\n";

        foreach ($events as $event) {
            $reply .= "• "
                . ($event['title'] ?? 'Untitled event')
                . " — "
                . (($event['venue'] ?? '') ?: 'To be announced')
                . "\n";
        }

        return ce_reply(trim($reply), ce_event_response($events));
    }

    // EVENT SCHEDULES
    if (ce_has($message, [
        'schedule',
        'when',
        'date',
        'time',
        'oras',
        'kailan',
        'anong araw',
        'what time',
        'what day',
        'anong petsa'
    ])) {
        $events = ce_get_events($pdo, 'upcoming');

        if (!$events) {
            return ce_reply(
                "There are no upcoming events scheduled "
                . "in the current records. Please check again later."
            );
        }

        $reply = "Here are the upcoming event dates and times:\n\n";

        foreach ($events as $event) {

            $date = $event['event_date'] ?? '';

            $date = $date && strtotime($date)
                ? date('M j, Y', strtotime($date))
                : 'Date to be announced';

            $start = !empty($event['start_time'])
                ? date('g:i A', strtotime($event['start_time']))
                : '';

            $end = !empty($event['end_time'])
                ? date('g:i A', strtotime($event['end_time']))
                : '';

            $time = $start
                ? $start . ($end ? ' – ' . $end : '')
                : 'Time to be announced';

            $reply .= "• " . ($event['title'] ?? 'Untitled event')
                . "\n  Date: $date"
                . "\n  Time: $time\n\n";
        }

        return ce_reply(
            trim($reply),
            ce_event_response($events)
        );
    }

    // EVENT ORGANIZERS
    if (ce_has($message, [
        'organizer',
        'organizers',
        'who organized',
        'who organizes',
        'organised',
        'organised by',
        'organized by',
        'sino ang nag organize',
        'sino organizer'
    ])) {
        $events = ce_get_events($pdo, 'upcoming');

        if (!$events) {
            return ce_reply(
                "There are no upcoming events with organizer information available."
            );
        }

        $reply = "Here are the organizers listed for upcoming events:\n\n";

        foreach ($events as $event) {
            $reply .= "• "
                . ($event['title'] ?? 'Untitled event')
                . " — "
                . (($event['organizer'] ?? '') ?: 'Not specified')
                . "\n";
        }

        return ce_reply(
            trim($reply),
            ce_event_response($events)
        );
    }

    // EVENT DETAILS
    if (ce_has($message, [
        'description',
        'details',
        'what is this event about',
        'tell me about',
        'about the event',
        'event info',
        'information about'
    ])) {
        $events = ce_get_events($pdo, 'upcoming');

        if (!$events) {
            return ce_reply(
                "There are no upcoming events available "
                . "to show details for right now."
            );
        }

        return ce_reply(
            "Here are the available details for upcoming events. "
            . "Open an event on the Events page for its full information:",
            ce_event_response($events)
        );
    }

    // GENERAL UPCOMING EVENTS
    if (ce_has($message, [
        'upcoming',
        'available event',
        'show event',
        'list event',
        'events',
        'event',
        'activities',
        'activity',
        'what can i join',
        'ano ang events',
        'mga event',
        'what is happening',
        'whats happening'
    ])) {
        $events = ce_get_events($pdo, 'upcoming');

        if (!$events) {
            return ce_reply(
                "There are no upcoming events available "
                . "in the current records. Please check the Events page again later."
            );
        }

        return ce_reply(
            "Here are the upcoming events currently listed in Campus Event Hub:",
            ce_event_response($events)
        );
    }

    // WEBSITE NAVIGATION
    if (ce_has($message, [
        'home page',
        'homepage',
        'navigate',
        'navigation',
        'how to use',
        'website',
        'site menu',
        'where can i find'
    ])) {
        return ce_reply(
            "Here's a quick guide to Campus Event Hub:\n\n"
            . "• HOME: View the website introduction and featured events.\n"
            . "• EVENTS: Browse event listings and event details.\n"
            . "• LOGIN: Sign in to your account.\n"
            . "• CREATE ACCOUNT: Register a new account.\n"
            . "• MY REGISTRATIONS: Review your registered events, "
            . "if available in your account menu.\n\n"
            . "The options available may depend on whether you are signed in."
        );
    }

    // DEFAULT RESPONSE
    return ce_reply(
        "Thanks for your question!\n\n"
        . "I can help you with:\n"
        . "• Upcoming and past campus events\n"
        . "• Event categories and descriptions\n"
        . "• Event schedules and venues\n"
        . "• Event organizers\n"
        . "• Registration and cancellation guidance\n"
        . "• Login and account assistance\n"
        . "• Website navigation\n\n"
        . "You can try asking:\n"
        . "“What events are coming up?”\n"
        . "“Where will the events be held?”\n"
        . "“How do I register?”\n"
        . "“What are the event categories?”\n\n"
        . "For a specific event, include its title in your question."
    );
}

// =============================================
// HANDLE CHAT REQUEST
// =============================================

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'reply' => 'Please send your question through the chat window.',
        'events' => []
    ]);

    exit;
}

$payload = json_decode(
    file_get_contents('php://input'),
    true
);

$message = is_array($payload)
    ? (string)($payload['message'] ?? '')
    : '';

try {

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $result = chatbot_reply($message, $pdo);

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {

    error_log('Campus chatbot error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'reply' => 'Sorry, the chatbot is temporarily unavailable. Please try again shortly. If the issue continues, contact the website administrator.',
        'events' => []
    ]);
}

exit;