<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$pageTitle = 'Verify Account';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL);
    exit;
}

// Check if verification user_id exists in session
if (!isset($_SESSION['verification_user_id'])) {
    header('Location: ' . SITE_URL . '/register.php');
    exit;
}

$user_id = $_SESSION['verification_user_id'];
$error = '';
$success = '';
$debug = '';

// Get user info
$query = "SELECT * FROM users WHERE id = " . (int)$user_id;
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    header('Location: ' . SITE_URL . '/register.php');
    exit;
}

$user = $result->fetch_assoc();

// Check if already verified
if (isset($user['email_verified']) && $user['email_verified'] == 1) {
    // User already verified, redirect to login
    unset($_SESSION['verification_user_id']);
    header('Location: ' . SITE_URL . '/login.php?verified=1');
    exit;
}

// Generate a math problem if needed or requested
function generateMathProblem() {
    $num1 = mt_rand(1, 20);
    $num2 = mt_rand(1, 20);
    
    $question = "$num1 + $num2 = ?";
    $answer = $num1 + $num2;
    
    return [
        'question' => $question,
        'answer' => $answer
    ];
}

// Check if we need to generate a question
if (!isset($_SESSION['math_question']) || !isset($_SESSION['math_answer']) || isset($_POST['new_question'])) {
    $problem = generateMathProblem();
    $_SESSION['math_question'] = $problem['question'];
    $_SESSION['math_answer'] = $problem['answer'];
    $_SESSION['math_expire'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $_SESSION['verification_attempts'] = 0;
}

// Process verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_answer'])) {
        // Verification form submitted
        $user_answer = trim($_POST['answer']);
        
        // Debug info
        $debug = "Your answer: $user_answer | Correct answer: {$_SESSION['math_answer']}";
        
        // Validate input
        if (empty($user_answer)) {
            $error = 'Answer is required';
        } elseif (!is_numeric($user_answer)) {
            $error = 'Answer must be a number';
        } elseif ((int)$user_answer !== (int)$_SESSION['math_answer']) {
            // Increase verification attempts
            $_SESSION['verification_attempts'] = ($_SESSION['verification_attempts'] ?? 0) + 1;
            $error = 'Incorrect answer. Please try again.';
            
            // Check if too many attempts
            if ($_SESSION['verification_attempts'] >= 5) {
                $error = 'Too many attempts. Please request a new question.';
                unset($_SESSION['math_question']);
                unset($_SESSION['math_answer']);
            }
        } elseif (strtotime($_SESSION['math_expire']) < time()) {
            $error = 'Verification time has expired. Please request a new question.';
            unset($_SESSION['math_question']);
            unset($_SESSION['math_answer']);
        } else {
            // Verify the account
            $update_query = "UPDATE users SET email_verified = 1 WHERE id = $user_id";
            
            if ($conn->query($update_query)) {
                $success = 'Account successfully verified!';
                
                // Clean up session
                unset($_SESSION['math_question']);
                unset($_SESSION['math_answer']);
                unset($_SESSION['math_expire']);
                unset($_SESSION['verification_attempts']);
                
                // Redirect after 3 seconds
                header('refresh:3;url=' . SITE_URL . '/login.php?verified=1');
            } else {
                $error = 'Verification failed. Please try again.';
            }
        }
    } elseif (isset($_POST['new_question'])) {
        // Generate new question through the logic above
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <h2 class="mb-2">Account Verification</h2>
                        <p class="text-muted">Please solve the math problem to verify your account</p>
                    </div>
                    
                    <?php if (!empty($debug)): ?>
                        <div class="alert alert-info"><?= $debug ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle mr-2"></i> <?= $success ?></h5>
                            <p class="mb-0">Redirecting you to login page...</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="fas fa-calculator fa-3x text-primary"></i>
                            </div>
                            <p>Please solve this math problem:</p>
                            <div class="math-question p-3 mb-3 bg-light rounded">
                                <h3 class="mb-0"><?= htmlspecialchars($_SESSION['math_question']) ?></h3>
                            </div>
                            <p class="small text-muted">
                                This is to verify you're a real person and to prevent automated registrations.
                            </p>
                        </div>
                        
                        <form method="post" action="" class="answer-form">
                            <div class="mb-4">
                                <label for="answer" class="form-label">Your Answer</label>
                                <input type="number" class="form-control form-control-lg text-center" id="answer" name="answer" placeholder="Enter your answer" required>
                                <div class="form-text text-center">
                                    Question expires at <?= date('h:i A', strtotime($_SESSION['math_expire'])) ?>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-4">
                                <button type="submit" name="verify_answer" class="btn btn-primary btn-lg">Verify Account</button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <p>Want a different question?</p>
                            <form method="post" action="">
                                <button type="submit" name="new_question" class="btn btn-link">Generate New Question</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.answer-form input {
    font-size: 24px;
    font-weight: 600;
}
.math-question {
    display: inline-block;
    border: 1px solid #e0e0e0;
}
</style>

<?php include 'includes/footer.php'; ?>