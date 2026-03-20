<?php
// transitions/draft_manager.php
session_start();
include('../includes/config.php');
include('../includes/session_check.php');

if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
}

// Handle AJAX requests for draft saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');

        $user_id = $_SESSION['user_id'];
        $action = $_POST['action'];

        if ($action === 'save_draft') {
                $draft_id = isset($_POST['draft_id']) ? (int)$_POST['draft_id'] : 0;
                $county_id = (int)$_POST['county_id'];
                $period = mysqli_real_escape_string($conn, $_POST['period']);
                $sections = mysqli_real_escape_string($conn, $_POST['sections']);
                $draft_data = mysqli_real_escape_string($conn, $_POST['draft_data']);

                if ($draft_id) {
                        // Update existing draft
                        $query = "UPDATE transition_drafts SET
                                            draft_data = '$draft_data',
                                            updated_at = NOW()
                                            WHERE draft_id = $draft_id AND user_id = $user_id";
                } else {
                        // Insert new draft
                        $query = "INSERT INTO transition_drafts
                                            (user_id, county_id, assessment_period, sections, draft_data, created_at, updated_at)
                                            VALUES ($user_id, $county_id, '$period', '$sections', '$draft_data', NOW(), NOW())";
                }

                if (mysqli_query($conn, $query)) {
                        $draft_id = $draft_id ?: mysqli_insert_id($conn);
                        echo json_encode(['success' => true, 'draft_id' => $draft_id]);
                } else {
                        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
                }
                exit();
        }

        if ($action === 'get_drafts') {
                $query = "SELECT d.*, c.county_name
                                    FROM transition_drafts d
                                    JOIN counties c ON d.county_id = c.county_id
                                    WHERE d.user_id = $user_id
                                    ORDER BY d.updated_at DESC";
                $result = mysqli_query($conn, $query);
                $drafts = [];
                while ($row = mysqli_fetch_assoc($result)) {
                        $drafts[] = $row;
                }
                echo json_encode($drafts);
                exit();
        }

        if ($action === 'delete_draft') {
                $draft_id = (int)$_POST['draft_id'];
                $query = "DELETE FROM transition_drafts WHERE draft_id = $draft_id AND user_id = $user_id";
                if (mysqli_query($conn, $query)) {
                        echo json_encode(['success' => true]);
                } else {
                        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
                }
                exit();
        }
}

// Redirect to main page if not AJAX
header('Location: transition_index.php');
exit();