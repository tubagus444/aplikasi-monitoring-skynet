window.monitoringMap = function (initialLocations) {
    return {
        map: null,
        markers: {},

        init() {
            this.map = L.map(this.$refs.map).setView([-6.37, 107.16], 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            this.$nextTick(() => {
                this.map.invalidateSize();
                this.updateMarkers(initialLocations || []);
            });
        },

        updateMarkers(locations) {
            if (!this.map) return;

            const activeIds = locations
                .filter(l => l.latitude && l.longitude)
                .map(l => String(l.id));

            Object.keys(this.markers).forEach(id => {
                if (!activeIds.includes(id)) {
                    this.map.removeLayer(this.markers[id]);
                    delete this.markers[id];
                }
            });

            const markerList = [];

            locations.forEach(loc => {
                if (!loc.latitude || !loc.longitude) return;

                const popup = `
                    <div style="min-width:160px;font-family:inherit;line-height:1.4">
                        <p style="font-weight:600;margin:0 0 4px 0;font-size:13px">${loc.name}</p>
                        <p style="margin:0;font-size:12px;color:#555">${loc.customer}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#888">${loc.damage_type}</p>
                        <p style="margin:2px 0 0;font-size:11px;color:#aaa;word-break:break-word">${loc.address}</p>
                        ${loc.last_update ? `<p style="margin:6px 0 0;font-size:11px;color:#aaa">&#128205; ${loc.last_update}</p>` : ''}
                    </div>`;

                const id = String(loc.id);

                if (this.markers[id]) {
                    this.markers[id].setLatLng([loc.latitude, loc.longitude]);
                    this.markers[id].getPopup().setContent(popup);
                    markerList.push(this.markers[id]);
                } else {
                    const marker = L.marker([loc.latitude, loc.longitude])
                        .bindPopup(popup)
                        .addTo(this.map);
                    this.markers[id] = marker;
                    markerList.push(marker);
                }
            });

            if (markerList.length === 1) {
                this.map.setView(markerList[0].getLatLng(), 15);
            } else if (markerList.length > 1) {
                const group = L.featureGroup(markerList);
                this.map.fitBounds(group.getBounds().pad(0.3));
            }
        },

        focusMarker(techId) {
            const marker = this.markers[String(techId)];
            if (marker) {
                this.map.setView(marker.getLatLng(), 17);
                marker.openPopup();
            }
        },
    };
};
