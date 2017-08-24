default:
	@echo "Knorke CLI"

test:
	phpunit --bootstrap tests/bootstrap.php

transform-ttl-to-nt:
	./scripts/transform-ttl-files-to-nt-files.sh
