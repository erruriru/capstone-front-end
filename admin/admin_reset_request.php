<?php
include "../conn.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);

    $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin) {
        // Generate raw token (sent to user)
            $token = bin2hex(random_bytes(32));

        // Hash token (stored in database)
            $hashedToken = hash('sha256', $token);

        // Token expiry
            $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));


        $update = $conn->prepare("
            UPDATE admins 
            SET reset_token = ?, reset_token_expiry = ? 
            WHERE id = ?
        ");
        $update->bind_param("ssi", $hashedToken, $expiry, $admin['id']);
        
        $update->execute();

        // In production: send via email
        $_SESSION['reset_link'] =
            "admin_reset_password.php?token=" . $token;

        $_SESSION['success'] = "Password reset link generated.";
    } else {
        $_SESSION['error'] = "Username not found.";
    }

    header("Location: admin_reset_request.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Password Reset</title>
    <link rel="stylesheet" href="../tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="h-screen flex items-center justify-center bg-secondary">
<div class="w-96 bg-primary p-8 rounded-lg shadow-xl">
    <h2 class="text-xl font-bold mb-4">Reset Admin Password</h2>

    <form method="POST">
        <label class="text-xs font-bold">USERNAME</label>
        <input type="text" name="username" required
               class="w-full p-2 mt-1 mb-4 rounded">

        <button type="submit"
                class="w-full py-2 border rounded hover:bg-black hover:text-white transition">
            REQUEST RESET
        </button>
    </form>

    <?php if (isset($_SESSION['reset_link'])): ?>
        <div class="mt-4 text-xs break-all">
            <strong>Demo Reset Link:</strong><br>
            <a class="underline text-blue-600"
               href="<?= $_SESSION['reset_link']; ?>">
                <?= $_SESSION['reset_link']; ?>
            </a>
        </div>
        <?php unset($_SESSION['reset_link']); ?>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['error'])): ?>
<script>
Swal.fire('Error','<?= $_SESSION['error']; ?>','error');
</script>
<?php unset($_SESSION['error']); endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<script>
Swal.fire('Success','<?= $_SESSION['success']; ?>','success');
</script>
<?php unset($_SESSION['success']); endif; ?>

</body>
</html>
