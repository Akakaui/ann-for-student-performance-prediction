<!-- Analytics Quick Overview Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up"></i> Platform Analytics Overview
                </h5>
                <a href="admin_analytics.php" class="btn btn-sm btn-primary">View Detailed Analytics</a>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo $total_users; ?></h4>
                            <small class="text-muted">Total Users</small>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-success"><?php echo $total_predictions; ?></h4>
                            <small class="text-muted">Predictions</small>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-info"><?php echo $active_users; ?></h4>
                            <small class="text-muted">Active Users</small>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-warning"><?php echo $total_groups; ?></h4>
                            <small class="text-muted">Groups</small>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-danger"><?php echo $active_lecturers; ?></h4>
                            <small class="text-muted">Active Lecturers</small>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6 mb-3">
                        <div>
                            <h4 class="text-secondary"><?php echo round($avg_confidence, 1); ?>%</h4>
                            <small class="text-muted">Avg Confidence</small>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Charts Row -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">User Registration Trend</h6>
                                <canvas id="quickRegChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">User Role Distribution</h6>
                                <canvas id="quickRoleChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>