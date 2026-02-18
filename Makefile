PLUGIN_NAME = packrelay
BUILD_DIR   = build
ZIP_FILE    = $(BUILD_DIR)/$(PLUGIN_NAME).zip

.PHONY: test zip clean

test:
	vendor/bin/phpunit

zip: clean
	mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	rsync -av --exclude-from=.distignore . $(BUILD_DIR)/$(PLUGIN_NAME)/
	cd $(BUILD_DIR) && zip -r $(PLUGIN_NAME).zip $(PLUGIN_NAME)/
	rm -rf $(BUILD_DIR)/$(PLUGIN_NAME)
	@echo "Built: $(ZIP_FILE)"

clean:
	rm -rf $(BUILD_DIR)
