<?php
include "../conn.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = trim($_POST["username"] ?? '');
  $password = $_POST["password"] ?? '';

  $stmt = $conn->prepare("
      SELECT id, username, password, failed_attempts, locked_until 
      FROM admins 
      WHERE username = ? 
      LIMIT 1
  ");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();
  $admin = $result->fetch_assoc();

  // Check if account exists
  if ($admin) {

      // Check if account is locked
      if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
          $_SESSION["error"] = "Account locked due to multiple failed attempts. Please reset your password.";
          header("Location: " . $_SERVER["PHP_SELF"]);
          exit();
      }

      // Verify password
      if (password_verify($password, $admin['password'])) {

          // Reset failed attempts on success
          $resetStmt = $conn->prepare("
              UPDATE admins 
              SET failed_attempts = 0, locked_until = NULL 
              WHERE id = ?
          ");
          $resetStmt->bind_param("i", $admin['id']);
          $resetStmt->execute();

          $_SESSION["username"] = $admin['username'];
          header("location: admin_dashboard.php");
          exit();

      } else {
          // Increment failed attempts
          $attempts = $admin['failed_attempts'] + 1;

          if ($attempts >= 5) {
              // Lock account
              $lockStmt = $conn->prepare("
                  UPDATE admins 
                  SET failed_attempts = ?, locked_until = NOW() 
                  WHERE id = ?
              ");
              $lockStmt->bind_param("ii", $attempts, $admin['id']);
              $lockStmt->execute();

              $_SESSION["error"] = "Too many failed attempts. Your account has been locked. Please reset your password.";
          } else {
              $updateStmt = $conn->prepare("
                  UPDATE admins 
                  SET failed_attempts = ? 
                  WHERE id = ?
              ");
              $updateStmt->bind_param("ii", $attempts, $admin['id']);
              $updateStmt->execute();

              $_SESSION["error"] = "Incorrect username or password. Attempt {$attempts}/5.";
          }

          header("Location: " . $_SERVER["PHP_SELF"]);
          exit();
      }
  } else {
      $_SESSION["error"] = "Incorrect username or password.";
      header("Location: " . $_SERVER["PHP_SELF"]);
      exit();
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>J.M. Apilado Resort - Admin Login</title>
  <link rel="stylesheet" href="../tailwind.css">
  <link rel="stylesheet" href="../css/theme.css">
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="w-screen h-screen bg-gradient-to-br bg-secondary flex justify-center items-center text-primary">
    <div id="login-container" class="w-96 bg-slate-950 flex flex-col items-start rounded-lg shadow-2xl bg-primary px-8 py-10">
      <h1 class="font-black text-3xl">Log in as Admin</h1>
      <form class="flex flex-col w-full py-5" method="POST">
        <div class="w-full flex flex-col">
          <span class="text-xs font-bold tracking-wider text-primary">USER NAME</span>
          <input type="text" name="username" class="mt-1 mb-5 p-2 rounded-md transition-all" maxlength="255" required />
        </div>
        <div class="w-full flex flex-col">
          <span class="text-xs font-bold tracking-wider text-primary">PASSWORD</span>
          <input type="password" name="password" class="mt-1 mb-5 p-2 rounded-md transition-all" minlength="8" maxlength="255" required />
        </div>
        <div class="w-full flex flex-row justify-end items-center">
          <button id="login-btn" type="submit" class="text-xs font-bold tracking-wider px-5 py-2 border border-[--color-text-primary] bg-transparent hover:bg-[--color-text-primary] hover:text-[--color-text-secondary] rounded-md cursor-pointer transition-all flex items-center gap-2">
            <span>LOGIN</span>
            <!-- <svg id="login-spinner" class="animate-spin h-4 w-4 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg> -->
          </button>
          <a href="admin_reset_request.php" 
              class="text-xs mt-3 text-right underline hover:text-accent">
                      Forgot password?
          </a>

          <script>
            
          </script>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.querySelector('form').addEventListener('submit', function() {
      const spinner = document.getElementById('login-spinner');
      const loginBtn = document.getElementById('login-btn');
      spinner.classList.remove('hidden');
      loginBtn.disabled = true;
    });

    gsap.from("#login-container", { scale: 0, duration: 0.25, ease: "easeInOut" });

    <?php if (isset($_SESSION['error'])): ?>
      Swal.fire({
        title: 'Error',
        text: '<?php echo $_SESSION['error']; ?>',
        icon: 'warning',
      });
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
  </script>
</body>
</html>
