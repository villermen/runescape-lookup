<?php
	namespace Villermen\RuneScape;

	use Villermen\View;
	use Villermen\RuneScape\Player;
	use Villermen\RuneScape\Common;

	require_once("Villermen/Autoload.php");
?>

<div class="chart"></div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
	google.charts.load("43", { "packages": [ "line" ]});
	google.charts.setOnLoadCallback(function() {
		var data = new google.visualization.DataTable();
		data.addColumn("date", "Date");

		<?php foreach(Common::getSkillNames() as $skillId => $skillName): ?>
			<?php if ($skillId == 0 || $skillId == 26) continue; ?>
			data.addColumn("number", "<?php echo $skillName; ?>");
		<?php endforeach; ?>

		data.addRows([
			<?php
				$player = new Player("Villermen");

				$db = new Database();
				$allDbStats = $db->query("SELECT * FROM stats WHERE player_id = ?", [
					$player->getDatabasePlayer()["id"]
				]);

				$chartData = "";

				foreach($allDbStats as $dbStats)
				{
					$stats = new Stats($dbStats["data"], new \DateTime($dbStats["time"]));

					echo "[ new Date(" . $stats->getTime()->format("Y") . ", " . ($stats->getTime()->format("n") - 1) . ", " . $stats->getTime()->format("j") . "), ";

					foreach(Common::getSkillNames() as $skillId => $skillName)
					{
						if ($skillId == 0 || $skillId == 25) continue;

						echo max($stats["skills"][$skillId]["xp"], 0) . ", ";
					}

					echo "], ";
				}
			?>
		]);

		var options = {
			chart: {
				title: "History for <?php echo $player->getName(); ?>"
			},
			height: 600,
			width: 1200,
			vAxis: {
				logScale: true,
				minValue: 0
			}
		};

		var chart = new google.charts.Line($(".chart")[0]);
		chart.draw(data, options);

		google.visualization.events.addListener(chart, 'select', function() {
			console.log(chart.getSelection());
		});
	});


</script>
