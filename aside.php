$query = 'SELECT student_course FROM students WHERE student_id = ?';
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($course_name);
    $stmt->fetch();
    $stmt->close();

    // Fetch course fee based on course_id
    $query = 'SELECT course_fee FROM courses WHERE course_name = ?';
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $course_name);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($course_fee);
    $stmt->fetch();
    $stmt->close();


    <?php foreach ($courses as $course): ?>
            <option value="<?php echo htmlspecialchars($course['course_id']); ?>" data-fee="<?php echo htmlspecialchars($course['course_fee']); ?>">
                <?php echo htmlspecialchars($course['course_name']); ?> - Ksh.<?php echo htmlspecialchars($course['course_fee']); ?>
            </option>
        <?php endforeach; ?>

        value="<?php echo htmlspecialchars($courses[0]['course_fee'] ?? ''); ?>"


        document.addEventListener('DOMContentLoaded', function() {
            // Event handler for when a parent is selected
            document.getElementById('parentSelect').addEventListener('change', function() {
                var parentId = this.value;
                var studentSelect = document.getElementById('studentSelect');

                if (parentId) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'validate2.php?parent_id=' + encodeURIComponent(parentId), true);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            studentSelect.innerHTML = xhr.responseText;
                        } else {
                            studentSelect.innerHTML = '<option value="">Error fetching students</option>';
                        }
                    };
                    xhr.send();
                } else {
                    studentSelect.innerHTML = '<option value="">Select a parent first</option>';
                }
            });

            // Event handler for when a student is selected
            document.getElementById('studentSelect').addEventListener('change', function() {
                var studentId = this.value;
                var studentNumberSelect = document.getElementById('studentNumber');

                if (studentId) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'validate3.php?student_id=' + encodeURIComponent(studentId), true);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            studentNumberSelect.innerHTML = xhr.responseText;
                        } else {
                            studentNumberSelect.innerHTML = '<option value="">Error fetching admission number</option>';
                        }
                    };
                    xhr.send();
                } else {
                    studentNumberSelect.innerHTML = '<option value="">Select a student</option>';
                }
            });
        });



        document.addEventListener('DOMContentLoaded', function() {
    var partialPaymentModal = document.getElementById('partialPaymentModal');
    partialPaymentModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var paymentId = button.getAttribute('data-id'); // Extract payment ID from data-id attribute
        var partialAmount = button.getAttribute('data-amount'); // Extract payment amount from data-amount attribute
        
        // Update the form fields with the payment ID and amount
        var paymentIdInput = partialPaymentModal.querySelector('#paymentId');
        var partialAmountInput = partialPaymentModal.querySelector('#partialAmount');
        
        paymentIdInput.value = paymentId;
        partialAmountInput.value = partialAmount;
    });
});