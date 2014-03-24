ignore /cache/
ignore /tmp/
ignore /storage/
ignore /\.idea/
ignore /tools/
ignore /vendor/

guard 'compass' do
	watch(%r{app/.+\.s[ac]ss$})
end

#guard 'behat' do
#  watch %r{^features/.+\.feature$}
#end

guard 'livereload', :host => "192.168.1.11" do
  watch(%r{app/.+\.(js|html|php|mustache|yaml)$})
  watch(%r{public/css/.+\.(css)})
end
