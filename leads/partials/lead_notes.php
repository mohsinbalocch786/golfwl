<!-- Notes / Interactions -->
    <?php if (!empty($lead['notes'])): ?>
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-sticky-note mr-1"></i> Notes</h3>
        </div>
        <div class="card-body">
            <p class="mb-0" style="white-space:pre-wrap"><?= htmlspecialchars($lead['notes']) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Note -->
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> Add Note / Interaction</h3>
        </div>
        <div class="card-body">
            <form method="post">
<?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_note">
                <div class="form-group mb-2">
                    <textarea name="note_text" class="form-control" rows="3"
                        placeholder="Write a note, log a call, or record an interaction…" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-save"></i> Save Note
                </button>
            </form>
        </div>
    </div>