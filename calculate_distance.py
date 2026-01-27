import math

def haversine_distance(lat1, lon1, lat2, lon2):
    """Calculate distance between two GPS points using Haversine formula"""
    R = 6371  # Earth's radius in kilometers
    
    lat1_rad = math.radians(lat1)
    lon1_rad = math.radians(lon1)
    lat2_rad = math.radians(lat2)
    lon2_rad = math.radians(lon2)
    
    dlat = lat2_rad - lat1_rad
    dlon = lon2_rad - lon1_rad
    
    a = math.sin(dlat/2)**2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(dlon/2)**2
    c = 2 * math.asin(math.sqrt(a))
    
    distance = R * c
    return distance

# Real GPS points from ESP32-01
point1 = (14.599500, 120.984200)  # Manila area
point2 = (31.099500, 137.484200)  # Japan area

distance = haversine_distance(point1[0], point1[1], point2[0], point2[1])
print(f"Distance between GPS points: {distance:.2f} km")
print(f"Point 1: {point1[0]}, {point1[1]} (Manila area)")
print(f"Point 2: {point2[0]}, {point2[1]} (Japan area)")
