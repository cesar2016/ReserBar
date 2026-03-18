const Spinner = ({ size = 24, color = "#fff" }) => {
    return (
        <div style={{
            width: size,
            height: size,
            border: "3px solid rgba(0,0,0,0.1)",
            borderLeftColor: color,
            borderRadius: "50%",
            animation: "spin 1s linear infinite"
        }} />
    );
};

export default Spinner;
