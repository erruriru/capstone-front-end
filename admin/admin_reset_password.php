<?php
include "../conn.php";
session_start();

$token = $_GET['token'] ?? '';

$stmt = $conn->prepare("
    SELECT id, reset_token_expiry 
    FROM admins 
    WHERE reset_token = ? 
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin || strtotime($admin['reset_token_expiry']) < time()) {
    die("Invalid or expired reset token.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $update = $conn->prepare("
            UPDATE admins 
            SET password = ?, 
                failed_attempts = 0, 
                locked_until = NULL, 
                reset_token = NULL, 
                reset_token_expiry = NULL 
            WHERE id = ?
        ");
        $update->bind_param("si", $hashed, $admin['id']);
        $update->execute();

        $_SESSION['success'] = "Password successfully reset.";
        header("Location: admin_login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password</title>
    <link rel="stylesheet" href="../tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="h-screen flex items-center justify-center bg-secondary">
<div class="w-96 bg-primary p-8 rounded-lg shadow-xl">
    <h2 class="text-xl font-bold mb-4">Set New Password</h2>

    <form method="POST">
        <label class="text-xs font-bold">NEW PASSWORD</label>
        <input type="password" name="password" minlength="8" required
               class="w-full p-2 mt-1 mb-4 rounded">

        <label class="text-xs font-bold">CONFIRM PASSWORD</label>
        <input type="password" name="confirm_password" minlength="8" required
               class="w-full p-2 mt-1 mb-4 rounded">

        <button type="submit"
                class="w-full py-2 border rounded hover:bg-black hover:text-white transition">
            RESET PASSWORD
        </button>
    </form>
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
